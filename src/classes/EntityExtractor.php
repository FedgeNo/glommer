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
    // Every entityType value the pipeline can produce: 'hashtag' (from
    // Delta::hashtags()) plus each spaCy NER label ner-extract.py emits,
    // lowercased to match its output. A ban target's type is validated
    // against this set so a bogus/over-length type is rejected up front
    // rather than blowing up on the varchar(16) column.
    public const ENTITY_TYPES = [
        'hashtag', 'person', 'org', 'gpe', 'loc', 'fac', 'product',
        'event', 'work_of_art', 'law', 'language', 'norp',
    ];

    // Matches bin/install.php's NER_VENV_DIR.
    private const NER_PYTHON = '/opt/glommer-ner/bin/python';
    private const NER_SCRIPT = __DIR__ . '/../../bin/ner-extract.py';
    private const NER_TIMEOUT_SECONDS = 60;

    /**
     * What language each text of the last extractBatch() turned out to be
     * written in, by the same index, null where nothing could read it.
     *
     * Kept here rather than returned because it is a by-product: the extractor
     * has to know the language to pick a model, and the answer is worth
     * recording, but every caller wants the entities and only one wants this.
     *
     * @var array<int, ?string>
     */
    private static array $detectedLanguages = [];

    /** @return array<int, ?string> by the same index extractBatch() was given */
    public static function detectedLanguages(): array
    {
        return self::$detectedLanguages;
    }

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

        $read = self::runNER($plain_texts);

        $ner_entities = [];
        self::$detectedLanguages = [];

        foreach ($plain_texts as $i => $plain_text) {
            $ner_entities[$i] = is_array($read[$i]['entities'] ?? null) ? $read[$i]['entities'] : [];
            $language = $read[$i]['language'] ?? null;
            self::$detectedLanguages[$i] = is_string($language) && $language !== '' ? $language : null;
        }

        $entities = [];

        foreach ($description_deltas as $i => $description_delta) {
            // Only what the model named is judged on how it reads. A hashtag is
            // somebody's own word for their own post and is lowercase as often
            // as not.
            $named = array_values(array_filter(
                $ner_entities[$i] ?? [],
                static fn (array $entity): bool => self::readsAsAName((string) $entity['value'])
            ));

            $entities[] = array_values(array_filter(
                array_merge($hashtag_entities[$i], $named),
                static fn (array $entity): bool => self::isFindable((string) $entity['value'])
            ));
        }

        return $entities;
    }

    /**
     * The shortest name the database can find again.
     *
     * A topic's page lists the posts that mention it, and that search is a
     * full-text match - which never sees a word shorter than InnoDB's minimum
     * token, three characters by default. So "UN" and "AI" can trend, be
     * stored, get a page, and that page is empty however much is written about
     * them. Better not to claim the topic exists.
     */
    private const SHORTEST_FINDABLE = 3;

    public static function isFindable(string $value): bool
    {
        return mb_strlen(trim($value)) >= self::SHORTEST_FINDABLE;
    }

    /** Beyond this there is enough of a word to be worth keeping regardless. */
    private const SHORT_ENOUGH_TO_MISREAD = 3;

    /**
     * The words that are never a subject, in the languages a relay actually
     * carries. Articles, determiners and the shortest prepositions - the parts
     * of a sentence an English model has no idea what to do with when the
     * sentence is not English.
     *
     * Written in one case and matched in any, because the same word arrives
     * both ways: "un homme" mid-sentence and "Un homme" at the start of one.
     * All-caps is the exception and is kept - see readsAsAName().
     */
    private const FUNCTION_WORDS = [
        // French
        'un', 'une', 'le', 'la', 'les', 'des', 'du', 'de', 'ce', 'cet', 'cette',
        'et', 'ou', 'dans', 'pour', 'avec', 'sur', 'par', 'au', 'aux', 'que', 'qui',
        // Spanish
        'el', 'los', 'las', 'una', 'unos', 'unas', 'del', 'y', 'en', 'por', 'para', 'con',
        // German
        'der', 'die', 'das', 'den', 'dem', 'ein', 'eine', 'einen', 'einem',
        'und', 'oder', 'mit', 'für', 'von', 'zu', 'im',
        // Italian
        'il', 'lo', 'gli', 'uno', 'di', 'della', 'nel',
        // Portuguese
        'o', 'a', 'os', 'as', 'um', 'uma', 'do', 'da', 'dos', 'das', 'em', 'no', 'na',
        // Dutch
        'het', 'een', 'of', 'op', 'voor', 'van',
        // Nordic
        'ett', 'det', 'och', 'eller', 'på', 'av', 'som',
        // English
        'the', 'an', 'and', 'or', 'in', 'on', 'at', 'to', 'is', 'it', 'this', 'that',
    ];

    /**
     * Whether a short thing the model named reads like a name at all.
     *
     * The model reads English and a relay carries every language there is, so
     * ordinary function words come back tagged as organizations - "un", "la",
     * "des". Frequency cannot tell them apart from real subjects, because
     * being everywhere across many authors is precisely what trending looks
     * for: one of them outranked every genuine topic on a live server.
     *
     * Two things catch them, because one is not enough. A short value written
     * in lowercase is not a name: a real one of three characters or fewer is
     * capitalised, US and AI and EU. But the same word is capitalised too when
     * it opens a sentence - "Un homme" - and case alone cannot tell that from
     * Bob, so a word that is never anybody's subject in any language a relay
     * carries is refused in whatever case it arrives.
     *
     * All-caps is kept either way. It is how an initialism is written, and UN
     * really is the United Nations while "Un" is only ever French.
     *
     * A script without capitals is deliberately never caught by the first
     * test: a value that has no upper and lower form of itself cannot be
     * lowercase, so a short name written in one is kept.
     *
     * Public because which values this keeps is the whole of the judgement,
     * and the tests hold it to account without spending a model call.
     */
    public static function readsAsAName(string $value): bool
    {
        $value = trim($value);
        $lowercase = mb_strtolower($value);

        // An initialism, whatever it spells. Checked before the word list so
        // UN, US, IT and AS survive it.
        if ($value === mb_strtoupper($value) && $value !== $lowercase) {
            return true;
        }

        if (isset(self::functionWords()[$lowercase])) {
            return false;
        }

        if (mb_strlen($value) > self::SHORT_ENOUGH_TO_MISREAD) {
            return true;
        }

        $has_case = mb_strtoupper($value) !== $lowercase;

        return !$has_case || $value !== $lowercase;
    }

    /** The word list as a lookup, built once rather than per entity. */
    private static function functionWords(): array
    {
        static $words = null;

        return $words ??= array_fill_keys(self::FUNCTION_WORDS, true);
    }

    /**
     * Runs every text through the NER subprocess in one call. Fails closed:
     * a missing venv, a subprocess error, or unparsable output all just fall
     * back to hashtag-only extraction (via the empty array here, merged into
     * extractBatch()'s results above) rather than breaking the trending
     * recompute over an optional enrichment step.
     *
     * @param string[] $plain_texts
     * @return array<int, array{language: ?string, entities: array<int, array{type: string, value: string}>}>
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

        // Drain stdout and stderr concurrently. Reading one to EOF before
        // touching the other can deadlock: if the Python side fills the
        // (~64 KB) stderr pipe buffer with spaCy warnings before it finishes
        // writing stdout, it blocks on the stderr write while this side is
        // blocked reading stdout, and neither progresses until `timeout`
        // kills it and the whole batch is discarded.
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $error_output = '';
        $open = [1 => $pipes[1], 2 => $pipes[2]];

        while ($open !== []) {
            $read = array_values($open);
            $write = null;
            $except = null;

            if (@stream_select($read, $write, $except, null) === false) {
                break;
            }

            foreach ($read as $stream) {
                $chunk = fread($stream, 65536);

                if ($chunk !== false && $chunk !== '') {
                    if ($stream === $pipes[1]) {
                        $output .= $chunk;
                    } else {
                        $error_output .= $chunk;
                    }
                }

                if (feof($stream)) {
                    fclose($stream);
                    unset($open[array_search($stream, $open, true)]);
                }
            }
        }

        $exit_code = proc_close($process);

        if ($exit_code !== 0) {
            error_log('EntityExtractor: NER subprocess failed (exit ' . $exit_code . '): ' . trim($error_output));

            return [];
        }

        $decoded = json_decode((string) $output, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
