<?php

declare(strict_types=1);

/**
 * Trending entities: which hashtags and named entities (people,
 * organizations, places, products, ...) are hot right now, scored by how
 * many DISTINCT people are talking about them - not how much any one person
 * is - within a fixed-size window of the newest top-level posts.
 *
 * The window is a POST COUNT, not a time window - the newest WINDOW_SIZE
 * top-level posts, full stop. No time in the query at all (no WHERE, no
 * ORDER BY, no LIMIT keyed off a timestamp) - the window auto-adjusts to
 * activity (slow posting -> spans more wall-clock time; a busy burst -> a
 * short one), zero tuning needed.
 *
 * Scoring counts each user AT MOST ONCE per entity, no matter how many times
 * they posted about it - one vote per person, so a single prolific poster
 * can't inflate an entity's score by repetition, and it's genuinely "how many
 * people" rather than "how many posts". A user's one vote is weighted by
 * exponential time-decay on their MOST RECENT qualifying post (a burst of
 * distinct people in the last hour outranks the same distinct-person count
 * spread across posts that are merely newest-by-id but days old, which
 * happens whenever posting is slow).
 *
 * Materialized into TrendingEntities, recomputed by bin/compute-trending.php
 * on a timer (~10-15 min, mirroring bin/backup.php's systemd-timer
 * precedent) rather than at read time - trending is read-often and
 * expensive-to-derive, and it's fine being minutes-stale. current() has a
 * lottery fallback (matches the codebase's lottery-sweep instinct elsewhere -
 * RateLimiter's pruning, LinkPreviewFetcher's staged-image sweep) that kicks
 * a synchronous recompute when the data is stale AND the timer apparently
 * isn't installed, so this degrades to "stale but self-healing" instead of
 * silently going dark.
 */
class Trending
{
    // The window: newest N top-level posts, not a time span - see class docblock.
    private const WINDOW_SIZE = 5000;

    // Recency-within-the-window decay, applied to each user's one vote. A
    // vote this many hours old (their most recent qualifying post) counts for
    // half of a brand-new one; the same age again, a quarter; etc.
    private const HALF_LIFE_HOURS = 6.0;

    // Abuse/noise guard: an entity needs at least this many DISTINCT authors
    // before it's allowed to trend at all, regardless of score - scoring
    // itself is already one-vote-per-user (see class docblock), so this is a
    // separate floor, not a redundant one: it stops even a single highly-
    // recent post (score close to 1.0) from qualifying on its own.
    private const MIN_DISTINCT_AUTHORS = 3;

    // current() self-heals via the lottery below once the freshest row is
    // older than this - matches the "still useful, just not brand new"
    // tolerance the class docblock describes.
    private const STALE_MINUTES = 30;

    // Settings key recompute() stamps on every run, whether or not any
    // entity actually qualified - isStale() reads this instead of
    // MAX(TrendingEntities.computedAt) so a quiet window that clears every
    // entity (nothing meets MIN_DISTINCT_AUTHORS) still counts as "just ran"
    // rather than looking like it never ran and re-triggering a synchronous
    // recompute on every near-future request.
    private const LAST_RUN_SETTING = 'trendingLastRecomputedAt';

    // Recompute is real work (pulls + scores the whole window) - a low-odds
    // lottery on a stale read avoids every concurrent stale request
    // recomputing at once, while still self-healing reasonably quickly under
    // normal traffic.
    private const RECOMPUTE_LOTTERY_ODDS = 20;

    /**
     * The current top trending entities, freshest-first by score.
     *
     * @return array<int, array{entityId: int, entityType: string, entityValue: string, score: float, postCount: int, userCount: int}>
     */
    public static function current(int $limit): array
    {
        if (self::isStale() && mt_rand(1, self::RECOMPUTE_LOTTERY_ODDS) === 1) {
            self::recompute();
        }

        $stmt = mysqli_prepare(DB::connection(), '
SELECT `entityId`, `entityType`, `entityValue`, `score`, `postCount`, `userCount`
    FROM `TrendingEntities`
    ORDER BY `score` DESC
    LIMIT ?
');
        mysqli_stmt_bind_param($stmt, 'i', $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $entities = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $entities[] = [
                'entityId' => (int) $row['entityId'],
                'entityType' => (string) $row['entityType'],
                'entityValue' => (string) $row['entityValue'],
                'score' => (float) $row['score'],
                'postCount' => (int) $row['postCount'],
                'userCount' => (int) $row['userCount'],
            ];
        }

        return $entities;
    }

    private static function isStale(): bool
    {
        $newest = Settings::get(self::LAST_RUN_SETTING);

        if ($newest === null) {
            return true;
        }

        $stale_seconds = self::STALE_MINUTES * 60;

        return (time() - strtotime($newest)) > $stale_seconds;
    }

    /**
     * Pulls the window, extracts + scores entities, and replaces
     * TrendingEntities with whatever currently qualifies. Every qualifying
     * entity is stamped with the same $computed_at for this run, and
     * anything NOT refreshed this run (fell out of the trending set
     * entirely) is deleted after - not a blind TRUNCATE-then-insert, so a
     * reader never sees a momentarily-empty table.
     */
    public static function recompute(): void
    {
        $rows = Post::globalFeedRows(self::WINDOW_SIZE)['rows'];
        $banned = self::bannedKeys();

        $entities_by_row = EntityExtractor::extractBatch(array_map(
            static fn (array $row): ?string => $row['descriptionDelta'] ?? null,
            $rows
        ));

        $now = time();
        $stats = [];

        foreach ($rows as $i => $row) {
            $entities = $entities_by_row[$i];

            if ($entities === []) {
                continue;
            }

            $created_at = strtotime((string) $row['createdAt']);
            $age_hours = $created_at !== false ? max(0, ($now - $created_at) / 3600) : 0;
            $weight = 0.5 ** ($age_hours / self::HALF_LIFE_HOURS);
            $user_id = (int) $row['userId'];

            foreach ($entities as $entity) {
                // Keyed on a case-folded value so "COVID" and "Covid" are one
                // entity (one vote pool, one ban match) instead of splitting
                // across two dictionary entries that only collide later at
                // the database's collation-insensitive unique key - the
                // first-seen casing is kept as the display value below.
                $key = $entity['type'] . "\0" . mb_strtolower($entity['value']);

                if (isset($banned[$key])) {
                    continue;
                }

                $stats[$key] ??= [
                    'type' => $entity['type'],
                    'value' => $entity['value'],
                    'postCount' => 0,
                    // One vote per user: keyed by userId, value is that
                    // user's single best (freshest/highest-weight) post
                    // mentioning this entity - repeats from the same user
                    // never add a second vote, only possibly raise their one.
                    'userWeights' => [],
                ];

                $stats[$key]['postCount']++;
                $stats[$key]['userWeights'][$user_id] = max($stats[$key]['userWeights'][$user_id] ?? 0.0, $weight);
            }
        }

        $mysqli = DB::connection();
        $computed_at = date('Y-m-d H:i:s', $now);

        foreach ($stats as $entity) {
            $user_count = count($entity['userWeights']);

            if ($user_count < self::MIN_DISTINCT_AUTHORS) {
                continue;
            }

            $entity['score'] = array_sum($entity['userWeights']);

            $stmt = mysqli_prepare($mysqli, '
INSERT INTO `TrendingEntities` (`entityType`, `entityValue`, `score`, `postCount`, `userCount`, `computedAt`)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `score` = VALUES(`score`), `postCount` = VALUES(`postCount`), `userCount` = VALUES(`userCount`), `computedAt` = VALUES(`computedAt`)
');
            mysqli_stmt_bind_param(
                $stmt,
                'ssdiis',
                $entity['type'],
                $entity['value'],
                $entity['score'],
                $entity['postCount'],
                $user_count,
                $computed_at
            );
            mysqli_stmt_execute($stmt);
        }

        $cleanup_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `TrendingEntities`
    WHERE `computedAt` < ?
');
        mysqli_stmt_bind_param($cleanup_stmt, 's', $computed_at);
        mysqli_stmt_execute($cleanup_stmt);

        // Stamped unconditionally, even when nothing qualified this run - see
        // LAST_RUN_SETTING's docblock.
        Settings::set(self::LAST_RUN_SETTING, $computed_at);
    }

    /**
     * Bans an entity from trending: a standing rule (BannedTrendingEntities),
     * not just a recomputed-away row, so it stays excluded even after
     * TrendingEntities is fully replaced on the next run. Also removes it
     * from TrendingEntities immediately, rather than waiting for the next
     * recompute, so the ban is visibly in effect right away.
     */
    public static function ban(string $entity_type, string $entity_value, int $moderator_id, ?string $reason): void
    {
        $mysqli = DB::connection();

        $ban_stmt = mysqli_prepare($mysqli, '
INSERT INTO `BannedTrendingEntities` (`entityType`, `entityValue`, `bannedBy`, `reason`)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `bannedBy` = VALUES(`bannedBy`), `reason` = VALUES(`reason`), `createdAt` = NOW()
');
        mysqli_stmt_bind_param($ban_stmt, 'ssis', $entity_type, $entity_value, $moderator_id, $reason);
        mysqli_stmt_execute($ban_stmt);

        $remove_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `TrendingEntities`
    WHERE `entityType` = ? AND `entityValue` = ?
');
        mysqli_stmt_bind_param($remove_stmt, 'ss', $entity_type, $entity_value);
        mysqli_stmt_execute($remove_stmt);

        // The durable, queryable record of who banned this entity (and the
        // reason) is the BannedTrendingEntities row itself - bannedBy plus
        // createdAt. The ModerationActions entry carries no targetId: a
        // trending entity is identified by a type+value string pair, not a
        // stable row id (TrendingEntities ids are regenerated every recompute,
        // so any id stored here would dangle within minutes).
        ModerationAction::log('banTrendingEntity', null, $entity_type, null);
    }

    public static function unban(string $entity_type, string $entity_value): void
    {
        $stmt = mysqli_prepare(DB::connection(), '
DELETE
    FROM `BannedTrendingEntities`
    WHERE `entityType` = ? AND `entityValue` = ?
');
        mysqli_stmt_bind_param($stmt, 'ss', $entity_type, $entity_value);
        mysqli_stmt_execute($stmt);

        ModerationAction::log('unbanTrendingEntity', null, $entity_type, null);
    }

    public static function isBanned(string $entity_type, string $entity_value): bool
    {
        $stmt = mysqli_prepare(DB::connection(), '
SELECT 1
    FROM `BannedTrendingEntities`
    WHERE `entityType` = ? AND `entityValue` = ?
');
        mysqli_stmt_bind_param($stmt, 'ss', $entity_type, $entity_value);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        return mysqli_stmt_num_rows($stmt) > 0;
    }

    /**
     * Every standing entity ban, newest first, for the moderation view that
     * lists and lifts them - joined to the banning moderator's username so the
     * "who" is shown, not just a bannedBy id.
     *
     * @return array<int, array{entityType: string, entityValue: string, reason: ?string, bannedByUsername: string, createdAt: string}>
     */
    public static function bannedEntities(): array
    {
        $stmt = mysqli_prepare(DB::connection(), '
SELECT `BannedTrendingEntities`.`entityType`, `BannedTrendingEntities`.`entityValue`, `BannedTrendingEntities`.`reason`, `BannedTrendingEntities`.`createdAt`, `Users`.`username` AS `bannedByUsername`
    FROM `BannedTrendingEntities`
    JOIN `Users` ON `Users`.`userId` = `BannedTrendingEntities`.`bannedBy`
    ORDER BY `BannedTrendingEntities`.`createdAt` DESC
');
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = [
                'entityType' => (string) $row['entityType'],
                'entityValue' => (string) $row['entityValue'],
                'reason' => $row['reason'],
                'bannedByUsername' => (string) $row['bannedByUsername'],
                'createdAt' => (string) $row['createdAt'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, true> "$type\0$value" => true, same case-folded
     *   key shape recompute()'s $stats array uses.
     */
    private static function bannedKeys(): array
    {
        $stmt = mysqli_prepare(DB::connection(), '
SELECT `entityType`, `entityValue`
    FROM `BannedTrendingEntities`
');
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $keys = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $keys[$row['entityType'] . "\0" . mb_strtolower($row['entityValue'])] = true;
        }

        return $keys;
    }

    /**
     * Whether glommer-trending.timer is confirmed armed and waiting for its
     * next run - mirrors UploadBatch::workerIsActive()'s three-way logic:
     * true/false only when systemd can be asked and gives a real answer,
     * null when it can't be determined at all (no systemctl, or SELinux
     * denying the web server's own status query). recompute() not running on
     * a schedule isn't fatal (current()'s lottery self-heal still covers it),
     * so a confirmed "not running" here is surfaced as a warning to admins,
     * not treated as a health-check failure.
     */
    public static function timerIsActive(): ?bool
    {
        if (trim((string) shell_exec('command -v systemctl 2>/dev/null')) === '') {
            return null;
        }

        $system = self::systemdUnitActiveState('systemctl is-active glommer-trending.timer 2>/dev/null');
        $user = self::systemdUnitActiveState('systemctl is-active --user glommer-trending.timer 2>/dev/null');

        if ($system === true || $user === true) {
            return true;
        }

        if ($system === false || $user === false) {
            return false;
        }

        return null;
    }

    private static function systemdUnitActiveState(string $command): ?bool
    {
        return match (trim((string) shell_exec($command))) {
            'active', 'activating', 'reloading' => true,
            'inactive', 'failed', 'deactivating' => false,
            default => null,
        };
    }
}
