<?php

declare(strict_types=1);

/**
 * Translates through one language pair, behind a Python process kept open for
 * as long as the worker is.
 *
 * Translator is the request-time path: one text, one sandboxed run of the
 * argos-translate command, and a reader waiting on it. This is the other kind
 * of job - a few thousand short strings in one go, where the model loading
 * costs more than the translating and loading it per string is the whole bill.
 * Here the model is loaded once and held for the run, at half a second to load
 * and about 550 MB to hold, which is why this is CLI-only.
 *
 * ctranslate2 directly rather than argostranslate: argostranslate keeps every
 * model it has ever loaded in a module-level list and offers nothing that
 * releases one, so a process that reaches across pairs grows until the machine
 * has no memory left. Here a model is one object one worker owns, and a pair
 * is a process.
 *
 * Texts go over in batches because that is where the throughput is: measured
 * at four times the rate of handing them over one at a time.
 */
class TranslationWorker
{
    /** Past this the padding in a batch costs more than the batching saves. */
    public const BATCH = 64;

    /** Argos's own default, and what a few hundred interface strings deserve. */
    public const CAREFUL = 4;

    /**
     * Greedy. Measured at 2.4x the rate of CAREFUL, which is nothing against
     * the interface and everything against a corpus.
     */
    public const QUICK = 1;

    private int $beam;

    private string $from;
    private string $to;

    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private bool $unavailable = false;

    private string $log = '';

    public function __construct(string $from, string $to, int $beam = self::CAREFUL)
    {
        $this -> from = $from;
        $this -> to = $to;
        $this -> beam = $beam;
    }

    /**
     * The languages a source language can be translated into, by what is
     * installed. Read off the packages rather than listed anywhere, so
     * installing one is all it takes to be offered.
     *
     * @return string[]
     */
    public static function targetsFrom(string $from): array
    {
        $found = [];

        foreach ((array) glob(Translator::PACKAGES_DIR . '/*') as $path) {
            $name = basename((string) $path);
            $pair = preg_replace('/^translate-|-[0-9_]+$/', '', $name);

            if (preg_match('/^' . preg_quote($from, '/') . '_([a-z]{2,3})$/', (string) $pair, $matches) === 1) {
                $found[] = $matches[1];
            }
        }

        sort($found);

        return array_values(array_unique($found));
    }

    /**
     * The translations of $texts, in the order given. A text that could not be
     * translated comes back empty rather than missing, so a caller can line the
     * answers up against what it asked about.
     *
     * @param string[] $texts
     * @return string[]
     */
    public function translate(array $texts): array
    {
        $empty = array_fill(0, count($texts), '');

        if ($texts === [] || !$this -> start()) {
            return $empty;
        }

        $request = json_encode(array_values($texts), JSON_UNESCAPED_UNICODE);

        if ($request === false || fwrite($this -> pipes[0], $request . "\n") === false) {
            $this -> close();

            return $empty;
        }

        $answer = fgets($this -> pipes[1]);

        if ($answer === false) {
            $this -> close();

            return $empty;
        }

        $translated = json_decode(trim($answer), true);

        if (!is_array($translated) || count($translated) !== count($texts)) {
            return $empty;
        }

        return array_map(fn ($text): string => is_string($text) ? $text : '', $translated);
    }

    /** Whatever the worker said before it stopped answering. */
    public function error(): string
    {
        $said = $this -> log !== '' && is_file($this -> log) ? (string) file_get_contents($this -> log) : '';
        $lines = array_filter(array_map('trim', explode("\n", $said)));

        return $lines === [] ? '' : (string) end($lines);
    }

    public function isAvailable(): bool
    {
        return !$this -> unavailable && is_file(Translator::PYTHON) && $this -> package() !== null;
    }

    /** The installed package directory for this pair, or null for neither layout. */
    public function package(): ?string
    {
        $pair = $this -> from . '_' . $this -> to;

        foreach ((array) glob(Translator::PACKAGES_DIR . '/*') as $path) {
            $name = basename((string) $path);

            if ($name === $pair || str_starts_with($name, 'translate-' . $pair . '-')) {
                return (string) $path;
            }
        }

        return null;
    }

    /**
     * Shuts the worker down and gives back the model's memory. Registered to
     * run at exit as well, so a caller that throws does not leave a model held.
     */
    public function close(): void
    {
        if ($this -> process === null) {
            return;
        }

        foreach ($this -> pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($this -> process);

        if ($this -> log !== '' && is_file($this -> log)) {
            unlink($this -> log);
        }

        $this -> process = null;
        $this -> pipes = [];
        $this -> log = '';
    }

    private function start(): bool
    {
        if ($this -> process !== null) {
            return true;
        }

        $package = $this -> package();

        if ($this -> unavailable || $package === null || !is_file(Translator::PYTHON)) {
            $this -> unavailable = true;

            return false;
        }

        $script = <<<'PYTHON'
import json
import os
import re
import sys

import ctranslate2
import sentencepiece

package, beam, source, target = sys.argv[1], int(sys.argv[2]), sys.argv[3], sys.argv[4]


class Sentencepiece:
    def __init__(self, path):
        self.model = sentencepiece.SentencePieceProcessor(path)

    def encode(self, text):
        return self.model.encode(text, out_type=str)

    def decode(self, tokens):
        decoded = self.model.decode(tokens)

        # Some packages ship one sentencepiece model for both sides, and the
        # target's pieces are not all in it - decode then hands back the word
        # boundary marker as literal text. Joining the pieces is what the
        # marker means anyway, so it is the answer whenever one survives.
        if "▁" in decoded:
            decoded = "".join(tokens).replace("▁", " ").strip()

        return decoded


class BPE:
    """The older packages ship subword-nmt BPE codes, which sentencepiece
    cannot read at all - it is a different format under a similar name. Argos
    carries the tokenizer for it, and it holds no model of its own."""

    def __init__(self, path, source, target):
        from argostranslate.tokenizer import BPETokenizer

        self.model = BPETokenizer(path, source, target)

    def encode(self, text):
        return self.model.encode(text)

    def decode(self, tokens):
        return self.model.decode(tokens)


tokenizer = None

if os.path.exists(os.path.join(package, "sentencepiece.model")):
    tokenizer = Sentencepiece(os.path.join(package, "sentencepiece.model"))
elif os.path.exists(os.path.join(package, "bpe.model")):
    tokenizer = BPE(os.path.join(package, "bpe.model"), source, target)

if tokenizer is None:
    sys.exit("no tokenizer in " + package)

# float32: several of the newer models collapse under the int8 quantisation
# ctranslate2 picks by default on this CPU, emitting one subword repeated to
# the length limit - silently, with no error and a plausible score.
translator = ctranslate2.Translator(
    os.path.join(package, "model"),
    device="cpu",
    compute_type="float32",
)

SENTENCE = re.compile(r"(?<=[.!?])\s+")


def translate(texts):
    # A sentence at a time, as Argos splits too: a model handed a whole
    # paragraph as one sequence drops the tail of it.
    pieces = []
    spans = []

    for text in texts:
        sentences = [s for s in SENTENCE.split(text) if s.strip()]
        spans.append((len(pieces), len(sentences)))
        pieces.extend(sentences)

    if not pieces:
        return ["" for _ in texts]

    results = translator.translate_batch(
        [tokenizer.encode(piece) for piece in pieces],
        beam_size=beam,
    )
    done = [tokenizer.decode(result.hypotheses[0]) for result in results]

    return [" ".join(done[start:start + length]) for start, length in spans]


for line in sys.stdin:
    line = line.strip()

    if not line:
        continue

    print(json.dumps(translate(json.loads(line)), ensure_ascii=False), flush=True)
PYTHON;

        // Kept rather than discarded: a package whose tokenizer cannot be read
        // fails at startup, and without this the only sign is every answer
        // coming back empty.
        $this -> log = (string) tempnam(sys_get_temp_dir(), 'translate-');

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $this -> log, 'w']];
        $command = [
            Translator::PYTHON, '-u', '-c', $script,
            $package, (string) $this -> beam, $this -> from, $this -> to,
        ];
        $process = @proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            $this -> unavailable = true;

            return false;
        }

        $this -> process = $process;
        $this -> pipes = $pipes;

        register_shutdown_function([$this, 'close']);

        return true;
    }
}
