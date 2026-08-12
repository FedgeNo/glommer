<?php

declare(strict_types=1);

/**
 * What language a post is written in, for every post rather than some.
 *
 * Reading a language was a by-product of the trending pass, which meant only
 * the posts that pass reads ever got one: top-level, from a human, and still
 * inside the newest few thousand. A bot's post, a reply, or anything that fell
 * past the window before a pass ran had no language and never would - and
 * translation cannot work without one.
 *
 * So it is its own job here. It needs no model, only langdetect, which is why
 * it can afford to look at everything: the cost that made the trending pass
 * batch its work is spaCy, and none of that is loaded for this.
 *
 * Run from bin/federation-worker.php rather than from the inbox, since a
 * subprocess per delivery would hold a PHP worker for the length of a Python
 * startup on the path a remote server is waiting on.
 */
class LanguageDetector
{
    private const PYTHON = '/opt/glommer-ner/bin/python';
    private const SCRIPT = __DIR__ . '/../../bin/ner-extract.py';

    /** Long enough to be worth a process, short enough to stay well inside the timeout. */
    public const BATCH_SIZE = 200;

    private const TIMEOUT_SECONDS = 60;

    public static function isAvailable(): bool
    {
        return is_executable(self::PYTHON) && is_file(self::SCRIPT);
    }

    /**
     * What one post is written in, read before it is stored.
     *
     * Read at the moment of writing rather than caught up with afterwards, so
     * a post has a language for as long as it exists and nothing ever has to
     * fall back to what its sender's account setting claims.
     *
     * Reading needs no model - langdetect is the whole of it - which is what
     * makes this affordable per post where the entity extractor never could
     * be. Null where the words could not be read, or where this installation
     * has no detector: fillInBatch() picks those up later.
     */
    public static function of(string $text): ?string
    {
        if (trim($text) === '' || !self::isAvailable()) {
            return null;
        }

        $languages = self::detect([$text]);
        $language = $languages[0] ?? null;

        return is_string($language) && $language !== '' ? $language : null;
    }

    /**
     * Fills in the language of posts that have none, newest first.
     *
     * Newest first because those are the ones somebody is reading now. The
     * backlog is worked through a batch per run rather than all at once, so a
     * server with years of posts catches up over hours instead of holding one
     * process for all of it.
     *
     * @return int how many posts were given a language this run
     */
    public static function fillInBatch(): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $posts = DB::rows('
SELECT `postId`, `descriptionDelta`, `description`
    FROM `Posts`
    WHERE `detectedLanguage` IS NULL
    ORDER BY `postId` DESC
    LIMIT ?
', 'stdClass', 'i', self::BATCH_SIZE);

        if ($posts === []) {
            return 0;
        }

        $texts = array_map(
            static fn (object $post): string => Delta::plainText(Delta::decode($post -> descriptionDelta))
                ?: (string) $post -> description,
            $posts
        );

        $languages = self::detect($texts);

        if ($languages === null) {
            return 0;
        }

        $filled = 0;

        foreach ($posts as $index => $post) {
            $language = $languages[$index] ?? null;

            // Nothing readable in it - punctuation, an emoji, a bare link.
            // Left null so a later run can try again if the post is edited,
            // rather than being written down as an answer.
            if (!is_string($language) || $language === '') {
                continue;
            }

            DB::run('
UPDATE `Posts`
    SET `detectedLanguage` = ?
    WHERE `postId` = ?
', 'si', $language, (int) $post -> postId);

            $filled++;
        }

        return $filled;
    }

    /**
     * The language of each text, in the order given, with null where there was
     * nothing to read - or null for the lot where the subprocess failed.
     *
     * @param string[] $texts
     * @return array<int, ?string>|null
     */
    private static function detect(array $texts): ?array
    {
        $command = sprintf(
            'timeout %d %s %s --detect',
            self::TIMEOUT_SECONDS,
            escapeshellarg(self::PYTHON),
            escapeshellarg(self::SCRIPT)
        );

        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return null;
        }

        fwrite($pipes[0], (string) json_encode(array_values($texts)));
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_close($process);

        if ($status !== 0) {
            error_log('LanguageDetector: detection failed (' . $status . ') ' . trim((string) $error));

            return null;
        }

        $decoded = json_decode((string) $output, true);

        return is_array($decoded) ? $decoded : null;
    }
}
