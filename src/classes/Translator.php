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

    /**
     * Deliberately far above what a translation uses, unlike the transcoder's,
     * which is sized to the job.
     *
     * Measured: 702MB resident for a four-sentence post, and over 3GB of
     * address space, because MKL reserves several times what it touches. A cap
     * anywhere near real use fails on the length of the text rather than on
     * anything being wrong - at 3GB a post split into one more sentence than
     * the last died with "mkl_malloc: failed to allocate memory", which reads
     * as a broken installation and is not one.
     *
     * So this catches a runaway and nothing else. What actually bounds the
     * memory is that the model is a fixed size, the input is capped, and only
     * so many of these run at once.
     */
    private const MAX_ADDRESS_SPACE_KB = 8388608;

    /**
     * How many translations may run at once.
     *
     * Each holds about 700MB while it runs and this machine has under two
     * spare, so a page of readers all pressing Translate is the one way this
     * feature could take the site down. Two is what fits.
     *
     * A reader who arrives when both are busy is told it did not work rather
     * than queued: the alternative is a request holding a PHP worker for the
     * length of somebody else's translation and then starting its own.
     */
    private const CONCURRENT = 2;

    /** Enough for the longest post that will be offered, and a cap on what a model is handed. */
    private const MAX_INPUT_BYTES = 8192;

    private const MAX_OUTPUT_BYTES = 262144;

    /**
     * The languages worth holding packages for: the ones this server has
     * actually seen people writing in (Posts.detectedLanguage), which is the
     * only honest basis for spending 158MB a direction. Adding one is adding
     * it here and re-running the installer; every language Argos publishes is
     * available, this list is a budget rather than a limit.
     *
     * English is the pivot rather than a preference - Argos routes any pair
     * through it, so the set costs two packages a language instead of one per
     * pairing.
     */
    public const LANGUAGES = ['de', 'es', 'fi', 'fr', 'it', 'ja', 'lv', 'pl', 'pt'];

    public const PIVOT = 'en';

    /**
     * Where the packages live, and how the command is told to behave.
     *
     * Not in a home directory: argos-translate defaults to the invoking user's,
     * so packages installed by the installer as root would be invisible to the
     * web server that has to read them.
     *
     * MiniSBD rather than the packaged Stanza model for splitting sentences.
     * Same translation, measured - and 7.3 seconds against 4.8, because Stanza
     * loads a second neural model just to find where the sentences end.
     *
     * One thread, so a translation can never take more than one of the four
     * cores, and niced so it yields to anything serving a page. It is CPU-bound
     * for its whole run and most of that is loading the model, not translating.
     *
     * @return array<string, string>
     */
    private static function environment(): array
    {
        return [
            'ARGOS_PACKAGES_DIR' => self::PACKAGES_DIR,
            'ARGOS_CHUNK_TYPE' => 'MINISBD',
            'OMP_NUM_THREADS' => '1',
            // The web server's home need not exist or be writable, and a
            // Python that cannot resolve one fails before it starts.
            'HOME' => sys_get_temp_dir(),
        ];
    }

    public const PACKAGES_DIR = '/opt/glommer-translate/packages';

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

        $slot = self::takeSlot();

        if ($slot === null) {
            error_log('Translator: all ' . self::CONCURRENT . ' slots busy, refusing rather than queueing');

            return null;
        }

        try {
            return self::run($text, $source, $target);
        } finally {
            self::releaseSlot($slot);
        }
    }

    /**
     * One of the concurrency slots, or null when they are all taken.
     *
     * Named database locks rather than a counter, because a counter has to be
     * put back and a PHP process that dies mid-translation would never do it -
     * a lock is released when the connection goes, however it goes. Asked for
     * with no timeout: waiting for a slot means holding a PHP worker for the
     * length of somebody else's translation, and then starting a five-second
     * one of your own.
     */
    private static function takeSlot(): ?string
    {
        for ($slot = 0; $slot < self::CONCURRENT; $slot++) {
            $name = 'glommer-translate-' . $slot;

            $result = mysqli_stmt_get_result(DB::run('
SELECT GET_LOCK(?, 0) AS `taken`
', 's', $name));

            $row = $result === false ? null : mysqli_fetch_assoc($result);

            if ((int) ($row['taken'] ?? 0) === 1) {
                return $name;
            }
        }

        return null;
    }

    private static function releaseSlot(string $name): void
    {
        mysqli_stmt_get_result(DB::run('
SELECT RELEASE_LOCK(?)
', 's', $name));
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
        $settings = '';

        foreach (self::environment() as $name => $value) {
            $settings .= $name . '=' . escapeshellarg($value) . ' ';
        }

        // Through env, not as a bare prefix: exec takes a command, so
        // "exec VAR=x cmd" has bash looking for a program called VAR=x.
        $inner = sprintf(
            'env %snice -n 10 %s --from-lang %s --to-lang %s',
            $settings,
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
