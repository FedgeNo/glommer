<?php

declare(strict_types=1);

/** The admin dashboard's readings and localized tile payloads. No schema or privileged account needed. */
class AdminStatus
{
    private const DAYS = 7;

    /** Explicit fixture readings never fall back to filesystem, network, or database access. */
    public static function snapshot(bool $overview = true, ?array $provided = null): array
    {
        $read = static function (string $key, callable $live) use ($provided): mixed {
            if ($provided !== null) {
                return $provided[$key] ?? null;
            }

            try {
                return $live();
            } catch (\Throwable $exception) {
                error_log('Admin status (' . $key . ') could not report: ' . $exception -> getMessage());

                return null;
            }
        };

        $services = $read('services', static fn (): array => ServiceStatus::snapshot()) ?? [];
        $counts = $read('counts', static fn (): SiteCountersData => SiteCounters::counts($overview));
        $totals = $read('deliveries', static fn (): array => [
            'delivered' => Statistic::since(Statistic::DELIVERED, self::DAYS),
            'undeliverable' => Statistic::since(Statistic::UNDELIVERABLE, self::DAYS),
        ]);
        $waiting = $read('pendingReads', static fn (): int => RelayFetch::pendingCount());
        $uploads = $read('uploads', static fn (): array => UploadBatch::queueDepth());
        $trending = $read('trending', static fn (): string => EntityRanker::lastRun());
        $reports = $read('reports', static fn (): int => (int) DB::row('SELECT COUNT(*) AS `total` FROM `Reports`', 'PostCountData') -> total);

        $words = Strings::for(AdminDashboard::class);
        $counter_words = Strings::for(SiteCounters::class);
        $missing = (string) (Strings::for(StatusBoard::class)['unavailable'] ?? '');
        $tiles = [];
        $format = static fn (string $key, array $values): string => strtr((string) ($counter_words[$key] ?? ''), $values);
        $tile = static function (string $id, string $symbol, string $href, string $value, string $detail, string $state = 'neutral') use (&$tiles, $words): void {
            $tiles[] = ['id' => $id, 'symbol' => $symbol, 'href' => $href, 'label' => (string) ($words[$id] ?? ''),
                'value' => $value, 'detail' => $detail, 'state' => $state];
        };
        $state = static fn (?bool $active): string => $active === null ? 'unknown' : ($active ? 'good' : 'bad');
        $value = static fn (?bool $active): string => $active === null ? $missing : (string) ($words[$active ? 'running' : 'stopped'] ?? '');
        $number = static fn (?int $count): string => $count === null ? '—' : ServerHealth::number($count);

        if ($overview) {
            $active = $read('activeMembers', static fn (): int => User::activeSince(self::DAYS));
            $tile('members', '👥', '/users', $number($counts ?-> members),
                $counts === null || $active === null ? $missing : $format('members', [
                    '{count}' => $number($counts -> members), '{joined}' => $number($counts -> joinedThisWeek), '{days}' => $number(self::DAYS),
                ]) . "\n" . $format('activeMembers', [
                    '{count}' => $number($active), '{posted}' => $number($counts -> postedThisWeek), '{days}' => $number(self::DAYS),
                ]), $counts === null || $active === null ? 'unknown' : 'neutral');
            $tile('posts', '📝', '/', $number($counts ?-> posts), $counts === null ? $missing : $format('posts', [
                '{count}' => $number($counts -> posts), '{recent}' => $number($counts -> postsThisWeek), '{days}' => $number(self::DAYS),
            ]), $counts === null ? 'unknown' : 'neutral');
        }

        $federation_state = $state($services['federation'] ?? null);
        $federation_detail = $missing;

        if ($counts !== null && $totals !== null && $waiting !== null) {
            $federation_detail = $format('queued', ['{count}' => $number($counts -> deliveriesQueued), '{failing}' => $number($counts -> deliveriesFailing)])
                . "\n" . $format('deliveries', ['{days}' => $number(self::DAYS), '{delivered}' => $number($totals['delivered']), '{undeliverable}' => $number($totals['undeliverable'])])
                . "\n" . $format('pendingReads', ['{count}' => $number($waiting)]);

            if ($totals['undeliverable'] > $totals['delivered']) {
                $federation_state = 'bad';
            } elseif ($federation_state === 'good' && $counts -> deliveriesFailing > 0) {
                $federation_state = 'warning';
            }
        } elseif ($federation_state === 'good') {
            $federation_state = 'unknown';
        }

        $tile('federation', '🌐', '#AdminRelays', $value($services['federation'] ?? null), $federation_detail, $federation_state);
        $tile('uploads', '📤', '#AdminServices', $value($services['uploads'] ?? null), $uploads === null ? $missing : strtr(
            (string) (Strings::for(UploadWorkerStatus::class)['queue'] ?? ''),
            ['{staging}' => $number($uploads['staging']), '{pending}' => $number($uploads['pending']), '{processing}' => $number($uploads['processing'])]
        ), $uploads === null && ($services['uploads'] ?? null) === true ? 'unknown' : $state($services['uploads'] ?? null));

        if ($overview) {
            // A real handshake is useful, but need not run on every short poll.
            $websocket = $read('websocket', static fn (): array => EnvironmentChecker::checkWebSocketServer());
            $websocket_words = Strings::for(WebSocketStatus::class);
            $tile('notifications', '🔔', '#AdminServices', $value($websocket['ok'] ?? null), $websocket === null ? $missing
                : ($websocket['ok'] ? (string) ($websocket_words['ok'] ?? '') : str_replace('{detail}', (string) $websocket['message'], (string) ($websocket_words['failed'] ?? ''))),
                $state($websocket['ok'] ?? null));
        }

        $trending_time = is_string($trending) && $trending !== '' ? strtotime($trending) : false;
        $trending_state = $state($services['trending'] ?? null);

        if ($trending_state === 'good') {
            if ($trending === null) {
                $trending_state = 'unknown';
            } elseif ($trending_time === false || time() - $trending_time > 1800) {
                $trending_state = 'warning';
            }
        }

        $tile('trending', '📈', '#AdminServices', $value($services['trending'] ?? null), $trending === null ? $missing
            : str_replace('{time}', self::time($trending_time === false ? null : $trending_time), (string) ($words['lastRun'] ?? '')), $trending_state);

        if ($overview) {
            $backup = $read('backups', static fn (): array => Backup::archiveStatus());
            $backup_time = $backup['time'] ?? null;
            $backup_state = $state($services['backups'] ?? null);

            if (!($backup['readable'] ?? false)) {
                $backup_state = 'unknown';
            } elseif ($backup_time === null) {
                $backup_state = 'bad';
            } elseif ($backup_state === 'good' && time() - $backup_time > 36 * 3600) {
                $backup_state = 'warning';
            }

            $tile('backups', '💾', '#ServerHealth', ($services['backups'] ?? null) === true ? (string) ($words['scheduled'] ?? '') : $value($services['backups'] ?? null),
                !($backup['readable'] ?? false) ? $missing : str_replace('{time}', self::time($backup_time), (string) ($words['lastBackup'] ?? '')), $backup_state);
        }

        $tile('moderation', '🚩', '/admin/reports', $number($reports), $reports === null ? $missing
            : str_replace('{count}', $number($reports), (string) ($words['reports'] ?? '')), $reports === null ? 'unknown' : ($reports > 0 ? 'warning' : 'good'));

        return ['health' => $read('health', static fn (): array => ServerHealth::readings()) ?? ServerHealth::readings([]), 'tiles' => $tiles];
    }

    private static function time(?int $timestamp): string
    {
        if ($timestamp === null) {
            return (string) (Strings::for(AdminDashboard::class)['none'] ?? '');
        }

        $format = new \IntlDateFormatter(Strings::locale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, 'UTC');

        return (string) $format -> format($timestamp) . ' UTC';
    }
}
