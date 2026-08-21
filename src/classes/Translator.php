<?php

declare(strict_types=1);

/**
 * Turning a post into another language, on this machine.
 *
 * SMaLL-100 (CTranslate2) is tried first: one model, 101 languages, run
 * through bin/small100-translate.py - the one bridge script here, needed
 * because CTranslate2 has no CLI of its own the way argos-translate does.
 * Argos Translate covers what SMaLL-100 does not (Esperanto, Basque, Kyrgyz,
 * Norwegian Bokmål, Brazilian Portuguese, Traditional Chinese - see
 * SMALL100_LANGUAGES) the way the upload pipeline runs ffmpeg: a program that
 * happens to be written in Python, invoked as a program.
 *
 * Both are loaded fresh for the length of one call and release everything
 * when the process exits. A resident model wants a gigabyte or two on its
 * own, and this box has about that much spare in total.
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

    // The venv's own interpreter, not the system one - the one CTranslate2,
    // transformers and sentencepiece are installed into.
    private const SMALL100_PYTHON = '/opt/glommer-translate/bin/python';

    private const SMALL100_SCRIPT = __DIR__ . '/../../bin/small100-translate.py';

    // Public: bin/install.php downloads the model here directly by this name.
    public const SMALL100_MODEL_DIR = '/opt/glommer-translate/models/small100';

    /**
     * Every language SMaLL-100 was trained on, read off its tokenizer's own
     * special tokens rather than transcribed from a paper - the model
     * publishes the list it will actually answer to. Six of this site's
     * locales are not on it: Esperanto, Basque and Kyrgyz are not in M2M-100
     * at all, and Norwegian Bokmål, Brazilian Portuguese and Traditional
     * Chinese collapse to a generic "no"/"pt"/"zh" the model has no way to
     * ask for specifically. Argos covers all six.
     *
     * @var string[]
     */
    private const SMALL100_LANGUAGES = [
        'af', 'am', 'ar', 'ast', 'az', 'ba', 'be', 'bg', 'bn', 'br', 'bs', 'ca', 'ceb', 'cs', 'cy', 'da',
        'de', 'el', 'en', 'es', 'et', 'fa', 'ff', 'fi', 'fr', 'fy', 'ga', 'gd', 'gl', 'gu', 'ha', 'he',
        'hi', 'hr', 'ht', 'hu', 'hy', 'id', 'ig', 'ilo', 'is', 'it', 'ja', 'jv', 'ka', 'kk', 'km', 'kn',
        'ko', 'lb', 'lg', 'ln', 'lo', 'lt', 'lv', 'mg', 'mk', 'ml', 'mn', 'mr', 'ms', 'my', 'ne', 'nl',
        'no', 'ns', 'oc', 'or', 'pa', 'pl', 'ps', 'pt', 'ro', 'ru', 'sd', 'si', 'sk', 'sl', 'so', 'sq',
        'sr', 'ss', 'su', 'sv', 'sw', 'ta', 'th', 'tl', 'tn', 'tr', 'uk', 'ur', 'uz', 'vi', 'wo', 'xh',
        'yi', 'yo', 'zh', 'zu',
    ];

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
     * English is the pivot rather than a preference - Argos publishes each
     * package as English and one other language and routes any other pairing
     * through it, so a language costs two packages instead of one per pairing.
     */
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
            // The web server's own home need not exist or be writable, and a
            // Python that cannot resolve one fails before it starts - so it is
            // given one that is its own. See STATE_DIR.
            'HOME' => self::STATE_DIR,
        ];
    }

    public const PACKAGES_DIR = '/opt/glommer-translate/packages';

    /**
     * A home of its own for the translator to keep its working files in.
     *
     * Not the temporary directory. Argos writes a sentence-splitter cache
     * under $HOME/.local/share, and anything shared is a directory somebody
     * else can get to first: on this server a CLI run left one owned by the
     * account that made it, and from then on the web server could not write
     * there - so every translation failed locally and went to the model
     * provider instead, which is exactly what running it here avoids. It
     * failed in the log and nowhere a reader could see.
     */
    public const STATE_DIR = '/opt/glommer-translate/state';

    public static function isAvailable(): bool
    {
        return is_executable(self::COMMAND);
    }

    public static function isSmall100Available(): bool
    {
        return is_executable(self::SMALL100_PYTHON)
            && is_file(self::SMALL100_SCRIPT)
            && is_dir(self::SMALL100_MODEL_DIR);
    }

    /** Whether SMaLL-100 was trained on this language at all. */
    public static function isSmall100Supported(?string $language): bool
    {
        $base = self::baseLanguage($language);

        return $base !== null && in_array($base, self::SMALL100_LANGUAGES, true);
    }

    /**
     * Whether this installation can translate anything at all.
     *
     * Any of three does: SMaLL-100, Argos where its packages are installed,
     * and the model provider for whatever neither covers - or for the whole
     * job on a server with none of the others. All three are optional, so
     * all being absent is a real state and the one case where there is
     * nothing to offer a reader.
     */
    public static function canTranslate(): bool
    {
        return self::isSmall100Available() || self::isAvailable() || OpenRouter::isEnabled();
    }

    /**
     * Why this pair cannot be translated, or null where nothing stops it.
     *
     * Every one of these is settled: asking again in a minute answers the same.
     * They are told apart so a reader can be told which it is, rather than
     * being sent away with "try again later" by something that will never
     * succeed.
     */
    public static function refusalFor(string $text, string $target, ?string $source): ?string
    {
        $source = self::baseLanguage($source);
        $target = self::baseLanguage($target);

        if (!self::canTranslate()) {
            return self::UNAVAILABLE;
        }

        if (self::readable($text) === '') {
            return self::NOTHING_TO_TRANSLATE;
        }

        if ($source === null) {
            return self::UNKNOWN_SOURCE;
        }

        if ($target === null || $source === $target) {
            return self::ALREADY_READABLE;
        }

        if (self::isSmall100Available() && self::isSmall100Supported($source) && self::isSmall100Supported($target)) {
            return null;
        }

        if (self::isAvailable() && self::isSupported($source) && self::isSupported($target)) {
            return null;
        }

        // Nothing installed can read this pair. A model can, so it is only a
        // refusal where there is no model to ask either.
        return OpenRouter::isEnabled() ? null : self::UNSUPPORTED_PAIR;
    }

    /**
     * The words as the model provider renders them, for everything Argos did
     * not do: a language it publishes no package for, a server that installed
     * none, or a translation that simply failed.
     *
     * Given the words and nothing else: the model is told to answer with the
     * translation alone, and anything it says about itself would be printed at
     * a reader as though the post had said it.
     */
    private static function byModel(string $text, string $source, string $target): ?string
    {
        $answer = OpenRouter::chat([
            [
                'role' => 'system',
                // Addresses, tags and handles are identifiers rather than
                // words: a translated #cats reaches a tag page nobody is
                // writing under, and a translated @name addresses nobody at
                // all. Said explicitly because a model asked to translate a
                // sentence will happily translate the words inside them.
                'content' => 'You are a translation engine. Translate the user\'s message from '
                    . $source . ' into ' . $target . '. Reply with the translation and nothing else -'
                    . ' no notes, no quotes around it, no explanation. Keep the line breaks.'
                    . ' Leave URLs, #hashtags and @mentions exactly as they are, including any'
                    . ' words inside them. If it is already in ' . $target . ', reply with it'
                    . ' unchanged.',
            ],
            ['role' => 'user', 'content' => $text],
        ], self::MODEL_MAX_TOKENS);

        $answer = $answer === null ? null : trim($answer);

        return $answer === null || $answer === '' ? null : $answer;
    }

    /** Room for a post of the length this refuses above, plus its punctuation. */
    private const MODEL_MAX_TOKENS = 2000;

    /** This server has no translator at all. */
    public const UNAVAILABLE = 'unavailable';

    /** Nothing in the post is words. */
    public const NOTHING_TO_TRANSLATE = 'nothing';

    /** Nobody has worked out what language the post is in. */
    public const UNKNOWN_SOURCE = 'unknownSource';

    /** It is already the language being asked for - or says it is. */
    public const ALREADY_READABLE = 'alreadyReadable';

    /** No package installed for one side of the pair. */
    public const UNSUPPORTED_PAIR = 'unsupportedPair';

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
        if (self::refusalFor($text, $target, $source) !== null) {
            return null;
        }

        $source = (string) self::baseLanguage($source);
        $target = (string) self::baseLanguage($target);
        $text = self::readable($text);

        // SMaLL-100 first where it covers the pair: one model for 101
        // languages rather than a package per pairing, still on this
        // machine and still free. Argos stands behind it for the six
        // locales SMaLL-100 was never trained on, and the model behind
        // both - every local way of coming back with nothing (unsupported
        // pair, every slot busy, the command failing or answering with
        // nothing at all) ends up there. A reader asked for the words in
        // their language; which of those went wrong is not their problem.
        return self::bySmall100($text, $source, $target)
            ?? self::byArgos($text, $source, $target)
            ?? self::byModel($text, $source, $target);
    }

    /** What SMaLL-100 makes of it, or null however it failed to. */
    private static function bySmall100(string $text, string $source, string $target): ?string
    {
        if (!self::isSmall100Available() || !self::isSmall100Supported($source) || !self::isSmall100Supported($target)) {
            return null;
        }

        $slot = self::takeSlot();

        if ($slot === null) {
            error_log('Translator: all ' . self::CONCURRENT . ' slots busy, going to Argos instead');

            return null;
        }

        try {
            return self::runSmall100($text, $source, $target);
        } finally {
            self::releaseSlot($slot);
        }
    }

    /** What Argos makes of it, or null however it failed to. */
    private static function byArgos(string $text, string $source, string $target): ?string
    {
        if (!self::isAvailable() || !self::isSupported($source) || !self::isSupported($target)) {
            return null;
        }

        $slot = self::takeSlot();

        if ($slot === null) {
            error_log('Translator: all ' . self::CONCURRENT . ' slots busy, going to the model instead');

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

    /**
     * The environment bin/small100-translate.py runs under: one thread for
     * the same reason Argos gets one, a home of its own for the same reason
     * (transformers resolves a cache directory even reading a local model),
     * and offline because the model is already on disk - nothing here should
     * ever reach out to Hugging Face's hub mid-request.
     *
     * @return array<string, string>
     */
    private static function small100Environment(): array
    {
        return [
            'OMP_NUM_THREADS' => '1',
            'HOME' => self::STATE_DIR,
            'HF_HUB_OFFLINE' => '1',
            'TRANSFORMERS_OFFLINE' => '1',
        ];
    }

    private static function runSmall100(string $text, string $source, string $target): ?string
    {
        $settings = '';

        foreach (self::small100Environment() as $name => $value) {
            $settings .= $name . '=' . escapeshellarg($value) . ' ';
        }

        $inner = sprintf(
            'env %snice -n 10 %s %s --from-lang %s --to-lang %s --model-dir %s',
            $settings,
            escapeshellarg(self::SMALL100_PYTHON),
            escapeshellarg(self::SMALL100_SCRIPT),
            escapeshellarg($source),
            escapeshellarg($target),
            escapeshellarg(self::SMALL100_MODEL_DIR)
        );

        $preamble = 'ulimit -v ' . self::MAX_ADDRESS_SPACE_KB . ' -t ' . self::CPU_TIMELIMIT . '; exec ' . $inner;
        $command = sprintf('timeout -k 10 %d bash -c %s', self::WALL_TIMEOUT, escapeshellarg($preamble));

        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return null;
        }

        fwrite($pipes[0], $text);
        fclose($pipes[0]);

        [$output, $errors] = read_both_pipes($pipes, self::MAX_OUTPUT_BYTES);

        if (!mb_check_encoding($output, 'UTF-8')) {
            $output = (string) mb_convert_encoding($output, 'UTF-8', 'UTF-8');
        }

        $status = proc_close($process);

        if ($status !== 0) {
            error_log('Translator: SMaLL-100 ' . $source . ' to ' . $target . ' exited ' . $status . ': ' . mb_strcut(trim($errors), 0, 300));

            return null;
        }

        $translated = trim($output);

        return $translated === '' ? null : $translated;
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

        // Both pipes drained together, or this deadlocks - see read_both_pipes.
        [$output, $errors] = read_both_pipes($pipes, self::MAX_OUTPUT_BYTES);

        // The cap counts bytes and a character is up to four of them, so
        // a translation long enough to be cut can end mid-character. Repaired
        // rather than passed on: what readable() does to what goes in, since a
        // half a character coming back is no more decodable than one going.
        if (!mb_check_encoding($output, 'UTF-8')) {
            $output = (string) mb_convert_encoding($output, 'UTF-8', 'UTF-8');
        }

        $status = proc_close($process);

        // Non-zero is a missing package for the pairing as often as anything
        // else, and the message says which - worth the log line, since the fix
        // is one argospm install away.
        if ($status !== 0) {
            // mb_strcut, so a message cut mid-character stays valid UTF-8 in
            // the log rather than ending in half of one.
            error_log('Translator: ' . $source . ' to ' . $target . ' exited ' . $status . ': ' . mb_strcut(trim($errors), 0, 300));

            return null;
        }

        $translated = trim($output);

        return $translated === '' ? null : $translated;
    }

    /** Whether this installation can read or write that language at all. */
    public static function isSupported(?string $language): bool
    {
        $base = self::baseLanguage($language);

        return $base !== null && in_array($base, self::installedLanguages(), true);
    }

    /**
     * The languages there is a package on disk for.
     *
     * Read from the package directory rather than listed here, because what a
     * server can do is what its admin downloaded: the installer fetches every
     * pair Argos publishes, but one can be missing for the length of a failed
     * download and a server can be told to skip translation entirely.
     *
     * @return string[]
     */
    public static function installedLanguages(): array
    {
        static $languages = null;

        if ($languages === null) {
            $languages = self::languagesIn(array_map('basename', glob(self::PACKAGES_DIR . '/*', GLOB_ONLYDIR) ?: []));
        }

        return $languages;
    }

    /**
     * The languages a set of package directory names covers.
     *
     * Two shapes are read, because Argos has published both and a server that
     * has been added to over time holds a mix: "en_es", and "translate-en_es-1_9"
     * with the package version on the end. Anything else down there - the
     * package manager's own working directories - names no pair and is skipped.
     *
     * @param string[] $directories
     *
     * @return string[]
     */
    public static function languagesIn(array $directories): array
    {
        $languages = [];

        foreach ($directories as $directory) {
            if (preg_match('/\A(?:translate-)?([a-z]{2,3})_([a-z]{2,3})(?:-[0-9_]+)?\z/', $directory, $matches) === 1) {
                $languages[$matches[1]] = true;
                $languages[$matches[2]] = true;
            }
        }

        return array_keys($languages);
    }

    /**
     * The language a tag names, or null where it names none.
     *
     * Most Argos packages use a language's ordinary base code. Three do not:
     * its package index calls Brazilian Portuguese `pb`, Traditional Chinese
     * `zt`, and Filipino `tl`. Those are not the standards-based locale tags
     * the interface and browser use, so translate them at this boundary.
     * A tag that is not a tag is refused here rather than reaching a command
     * line.
     */
    public static function baseLanguage(?string $tag): ?string
    {
        $tag = strtolower(str_replace('_', '-', trim((string) $tag)));

        if (preg_match('/\Apt-br(?:-|\z)/', $tag) === 1 || $tag === 'pb') {
            return 'pb';
        }

        if (preg_match('/\Azh-(?:hant|tw|hk|mo)(?:-|\z)/', $tag) === 1 || $tag === 'zt') {
            return 'zt';
        }

        if (preg_match('/\Afil(?:-|\z)/', $tag) === 1 || preg_match('/\Atl(?:-|\z)/', $tag) === 1) {
            return 'tl';
        }

        $base = explode('-', $tag)[0];

        return preg_match('/\A[a-z]{2,3}\z/', $base) === 1 ? $base : null;
    }

    /**
     * The package names in a listing from argospm, for bin/install.php.
     *
     * Both of its listings, because the installer wants the difference between
     * them and they are printed differently: "argospm search" names a package
     * and then describes it ("translate-en_es: en -> es"), "argospm list" names
     * it alone. A line naming no package is skipped rather than trusted -
     * these come off a command's standard output, which carries whatever else
     * it has to say about a slow index.
     *
     * Reduced to the pair it holds, so anything trailing the name - a version,
     * a description - cannot make a package that is installed look missing.
     * The difference between the two listings is a download of a hundred
     * megabytes a package, on a connection somebody pays for by the gigabyte.
     *
     * @return string[]
     */
    public static function packagesIn(string $listing): array
    {
        $packages = [];

        foreach (explode("\n", $listing) as $line) {
            if (preg_match('/\Atranslate-([a-z]{2,3})_([a-z]{2,3})\b/', trim($line), $matches) === 1) {
                $packages[] = 'translate-' . $matches[1] . '_' . $matches[2];
            }
        }

        return array_values(array_unique($packages));
    }
}
