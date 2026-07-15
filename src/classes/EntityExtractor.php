<?php

declare(strict_types=1);

/**
 * The trending pipeline's extraction step, two sources per post: the
 * explicit, user-supplied hashtags a post's body carries (parsed via
 * Delta::hashtags() - the exact same function the real hashtag system uses
 * to index a post at write time, so there's no way for this to drift from
 * what a post's real hashtags are), plus named entities (people,
 * organizations, places, products, ...) pulled from the post's plain text by
 * a spaCy model running in the isolated venv bin/install.php's
 * ensure_ner_environment() provisions at /opt/glommer-ner. Deliberately NOT
 * sourced from Posts.keywords (the denormalized flat copy) - that column
 * exists for FULLTEXT search and isn't guaranteed to hold only hashtags (a
 * pre-hashtag-feature or otherwise irregularly-populated row could carry
 * anything there).
 *
 * Batched, not one post at a time: spaCy's model load dominates the runtime
 * of a single invocation, so extractBatch() runs the whole trending window
 * through one subprocess via nlp.pipe() rather than spawning a process per
 * post.
 */
class EntityExtractor
{
    // Matches bin/install.php's NER_VENV_DIR.
    private const NER_PYTHON = '/opt/glommer-ner/bin/python';
    private const NER_SCRIPT = __DIR__ . '/../../bin/ner-extract.py';
    private const NER_TIMEOUT_SECONDS = 60;

    /**
     * @param array<int, ?string> $description_deltas
     * @return array<int, array<int, array{type: string, value: string}>> Same
     *   length and order as $description_deltas.
     */
    public static function extractBatch(array $description_deltas): array
    {
        $description_deltas = array_values($description_deltas);

        $hashtag_entities = [];
        $plain_texts = [];

        foreach ($description_deltas as $description_delta) {
            if ($description_delta === null) {
                $hashtag_entities[] = [];
                $plain_texts[] = '';

                continue;
            }

            $ops = Delta::decode($description_delta);

            $hashtag_entities[] = array_map(
                static fn (string $tag): array => ['type' => 'hashtag', 'value' => $tag],
                Delta::hashtags($ops)
            );
            $plain_texts[] = Delta::plainText($ops);
        }

        $ner_entities = self::runNER($plain_texts);

        $entities = [];

        foreach ($description_deltas as $i => $description_delta) {
            $entities[] = array_merge($hashtag_entities[$i], $ner_entities[$i] ?? []);
        }

        return $entities;
    }

    /**
     * Runs every text through the NER subprocess in one call. Fails closed:
     * a missing venv, a subprocess error, or unparsable output all just fall
     * back to hashtag-only extraction (via the empty array here, merged into
     * extractBatch()'s results above) rather than breaking the trending
     * recompute over an optional enrichment step.
     *
     * @param string[] $plain_texts
     * @return array<int, array<int, array{type: string, value: string}>>
     */
    private static function runNER(array $plain_texts): array
    {
        if (!is_executable(self::NER_PYTHON) || !is_file(self::NER_SCRIPT)) {
            return [];
        }

        $command = sprintf(
            'timeout %d %s %s',
            self::NER_TIMEOUT_SECONDS,
            escapeshellarg(self::NER_PYTHON),
            escapeshellarg(self::NER_SCRIPT)
        );

        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return [];
        }

        fwrite($pipes[0], json_encode($plain_texts));
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $error_output = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exit_code = proc_close($process);

        if ($exit_code !== 0) {
            error_log('EntityExtractor: NER subprocess failed (exit ' . $exit_code . '): ' . trim($error_output));

            return [];
        }

        $decoded = json_decode((string) $output, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
