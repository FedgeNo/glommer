<?php

declare(strict_types=1);

/**
 * Turning a post into another language, on this machine.
 *
 * Runs the argos-translate command the way the upload pipeline runs ffmpeg: a
 * program that happens to be written in Python, invoked as a program. Nothing
 * here is written in Python and there is no bridge script between the two.
 *
 * A model is loaded for the length of one call and nothing is held between
 * them. A resident translation server wants a gigabyte or two, and this box
 * has about that much spare in total.
 *
 * It replaced asking a free LLM router, which answered the same request in 1.5
 * seconds and then in 16.9 - the router picks a different model every call, so
 * a reader pressing Translate had no idea whether to wait. It also means what
 * somebody wrote no longer leaves the server to be translated.
 */
class Translator
{
    // Matches bin/install.php's TRANSLATE_VENV_DIR.
    private const COMMAND = '/opt/glommer-translate/bin/argos-translate';

    /**
     * The OS-level limits a PHP memory_limit cannot provide, the same three
     * the transcoder runs under: wall clock, CPU time, address space.
     *
     * Generous, because they exist to stop a runaway rather than to size the
     * job - a model that loads slowly on a busy box must not be killed
     * mid-sentence, and Python reserves far more address space than it uses.
     */
    private const WALL_TIMEOUT = 60;
    private const CPU_TIMELIMIT = 45;
    private const MAX_ADDRESS_SPACE_KB = 3145728;

    /** Enough for the longest post that will be offered, and a cap on what a model is handed. */
    private const MAX_INPUT_BYTES = 8192;

    private const MAX_OUTPUT_BYTES = 262144;

    /**
     * The languages worth holding packages for: what this server has actually
     * seen people writing in, which is the only honest basis for spending a
     * hundred megabytes a direction. Adding one is adding it here and
     * re-running the installer.
     *
     * English is the pivot rather than a preference - Argos routes any pair
     * through it, so the set costs two packages a language instead of one per
     * pairing.
     */
    public const LANGUAGES = ['es', 'fr', 'de', 'pt', 'it', 'nl', 'pl', 'ru', 'ja', 'zh'];

    public const PIVOT = 'en';

    public static function isAvailable(): bool
    {
        return is_executable(self::COMMAND);
    }

    /**
     * The text in $target, or null where it could not be done - no
     * environment, no package for the pairing, nothing readable back.
     *
     * Both languages are reduced to their base tag: Argos has a package for
     * Portuguese, not for pt-BR, and asking for a tag it has never heard of
     * fails where asking for the language would have worked.
     */
    public static function translate(string $text, string $target, ?string $source): ?string
    {
        $source = self::baseLanguage($source);
        $target = self::baseLanguage($target);
        $text = self::readable($text);

        if (!self::isAvailable() || $source === null || $target === null || $source === $target || $text === '') {
            return null;
        }

        return self::run($text, $source, $target);
    }

    /**
     * The text with everything that cannot survive the trip taken out.
     *
     * The post itself never reaches a command line - it is written to the
     * process's stdin, which the command reads when both language flags are
     * given. That is not about quoting, which escapeshellarg does correctly;
     * it is that quoting protects the shell and not the program's own
     * argument parsing, and a post beginning with a dash would be read as a
     * flag however perfectly it were quoted.
     *
     * What is left to guard is what the far side cannot decode: a NUL ends a
     * C string halfway through somebody's sentence, and a byte that is not
     * valid UTF-8 raises inside Python before it translates anything. Both
     * are removed rather than passed on to fail.
     */
    private static function readable(string $text): string
    {
        $text = ControlCharacters::strip(str_replace("\0", '', $text));

        // Substituting rather than refusing: one bad byte in a long post is
        // not a reason to hand back nothing.
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return trim(mb_strcut($text, 0, self::MAX_INPUT_BYTES));
    }

    private static function run(string $text, string $source, string $target): ?string
    {
        // Same shape as UploadProcessor::guardedCommand(): ulimit for what
        // timeout cannot cap, exec so timeout supervises the command itself
        // rather than a shell that outlives it.
        $inner = sprintf(
            '%s --from-lang %s --to-lang %s',
            escapeshellarg(self::COMMAND),
            escapeshellarg($source),
            escapeshellarg($target)
        );

        $preamble = 'ulimit -v ' . self::MAX_ADDRESS_SPACE_KB . ' -t ' . self::CPU_TIMELIMIT . '; exec ' . $inner;
        $command = sprintf('timeout -k 10 %d bash -c %s', self::WALL_TIMEOUT, escapeshellarg($preamble));

        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return null;
        }

        fwrite($pipes[0], $text);
        fclose($pipes[0]);

        // Both pipes drained together. Reading one to EOF first can deadlock:
        // the far side blocks writing a full stderr buffer while this side
        // blocks reading stdout, and neither moves until the timeout kills it.
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $errors = '';
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
                        $output .= substr($chunk, 0, max(0, self::MAX_OUTPUT_BYTES - strlen($output)));
                    } else {
                        $errors .= $chunk;
                    }
                }

                if (feof($stream)) {
                    fclose($stream);
                    unset($open[array_search($stream, $open, true)]);
                }
            }
        }

        $status = proc_close($process);

        // Non-zero is a missing package for the pairing as often as anything
        // else, and the message says which - worth the log line, since the fix
        // is one argospm install away.
        if ($status !== 0) {
            error_log('Translator: ' . $source . ' to ' . $target . ' exited ' . $status . ': ' . substr(trim($errors), 0, 300));

            return null;
        }

        $translated = trim($output);

        return $translated === '' ? null : $translated;
    }

    /**
     * The language a tag names, or null where it names none.
     *
     * Argos packages are per language, so "pt-BR" and "pt" are the same
     * package and "en-GB" is English. A tag that is not a tag is refused here
     * rather than reaching a command line.
     */
    public static function baseLanguage(?string $tag): ?string
    {
        $base = strtolower(explode('-', trim((string) $tag))[0]);

        return preg_match('/\A[a-z]{2,3}\z/', $base) === 1 ? $base : null;
    }

    /**
     * The package names this installation wants, for bin/install.php.
     *
     * Both directions per language, because a package translates one way and
     * a reader wants both: their language into the pivot, and the pivot into
     * theirs.
     *
     * @return string[]
     */
    public static function wantedPackages(): array
    {
        $packages = [];

        foreach (self::LANGUAGES as $language) {
            $packages[] = 'translate-' . self::PIVOT . '_' . $language;
            $packages[] = 'translate-' . $language . '_' . self::PIVOT;
        }

        return $packages;
    }
}
