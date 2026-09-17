<?php

declare(strict_types=1);

/**
 * Direct PHP translation through the current Google Translate webapp RPC.
 * https://github.com/ssut/py-googletrans (MIT; see googletrans-license.txt).
 *
 * The first request bootstraps an in-memory cookie and connection session.
 * No daemon, configuration changes, or automatic retries. Only failure
 * timestamps are shared through the existing rate limiter.
 * Remote failures leave unanswered inputs empty;
 * error() explains the failure and this instance stops making requests.
 */
class GoogleTranslation
{
    public const COOLDOWN_SECONDS = 300;
    public const PATIENCE = 10;
    private const HOME = 'https://translate.google.com/';
    private const ENDPOINT = 'https://translate.google.com/_/TranslateWebserverUi/data/batchexecute';
    private const RPC = 'MkEWBc';
    public const MAX_CHARACTERS = 5000;
    private const MAX_RESPONSE_BYTES = 1048576;
    private const MAX_HOME_BYTES = 8388608;

    private string $from;
    private string $to;
    private int $patience;
    private ?GoogleTranslationException $error = null;
    private bool $busy = false;
    /** @var array<string, \CurlHandle> In-memory cookie and connection session. */
    private array $handles = [];
    /** @var array<string, array{session: string, build: string}> */
    private array $sessions = [];
    private int $requestId = 100001;

    public function __construct(string $from, string $to, int $patience = self::PATIENCE)
    {
        $this -> from = self::language($from, true);
        $this -> to = self::language($to, false);

        if ($patience < 1 || $patience > 120) {
            throw new \InvalidArgumentException('Translation timeout must be between 1 and 120 seconds.');
        }

        $this -> patience = $patience;
    }

    /** Accept Google language codes; auto is valid only for the source. */
    private static function language(string $locale, bool $source): string
    {
        $locale = strtolower(trim($locale));
        $locale = match ($locale) {
            'pb' => 'pt',
            'zt' => 'zh-tw',
            'nb' => 'no',
            default => $locale,
        };

        if (($source && $locale === 'auto') || ($locale !== 'auto' && preg_match('/^[a-z]{2,3}(?:-[a-z]{2,4})?$/D', $locale) === 1)) {
            return $locale;
        }

        throw new \InvalidArgumentException('Invalid translation language code.');
    }

    public function isAvailable(): bool
    {
        return !$this -> busy && !$this -> coolingDown();
    }

    public function error(): ?GoogleTranslationException
    {
        return $this -> error;
    }

    /** @param string[] $texts @return string[] Under the original input keys. */
    public function translate(array $texts): array
    {
        // Validate the whole call before sending any of its text remotely.
        foreach ($texts as $text) {
            if (!is_string($text) || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text, 'UTF-8') > self::MAX_CHARACTERS) {
                throw new \InvalidArgumentException('Translation inputs must be valid UTF-8 strings of at most 5000 characters.');
            }
        }

        $answers = array_fill_keys(array_keys($texts), '');
        $this -> error = null;
        $this -> busy = false;

        if ($texts === []) {
            return $answers;
        }

        if (!$this -> acquireTranslationLock()) {
            $this -> busy = true;
            $this -> error = new GoogleTranslationException('Google translation is busy.');

            return $answers;
        }

        try {
            return $this -> translateLocked($texts);
        } finally {
            $this -> releaseTranslationLock();
        }
    }

    private function translateLocked(array $texts): array
    {
        $answers = array_fill_keys(array_keys($texts), '');

        foreach ($texts as $key => $text) {
            if ($this -> coolingDown()) {
                $this -> error = new GoogleTranslationException('Google translation is cooling down after an error.');
                break;
            }

            if (trim($text) === '' || $this -> from === $this -> to) {
                $answers[$key] = $text;
                continue;
            }

            try {
                $session = $this -> session();
                $query = http_build_query([
                    'rpcids' => self::RPC, 'source-path' => '/',
                    'f.sid' => $session['session'], 'bl' => $session['build'],
                    'hl' => 'en', 'soc-app' => 1, 'soc-platform' => 1, 'soc-device' => 1,
                    '_reqid' => $this -> requestId++, 'rt' => 'c',
                ], '', '&', PHP_QUERY_RFC3986);
                $payload = json_encode([[$text, $this -> from, $this -> to, 1, null, 2], []], JSON_THROW_ON_ERROR);
                $form = http_build_query([
                    'f.req' => json_encode([[[self::RPC, $payload, null, 'generic']]], JSON_THROW_ON_ERROR),
                ], '', '&', PHP_QUERY_RFC3986);
                $response = $this -> request(self::ENDPOINT . '?' . $query, $form);
                self::checkStatus($response);
                $answers[$key] = $this -> answer($response['body']);
            } catch (GoogleTranslationException $error) {
                $this -> error = $error;
                $this -> coolDown();
                error_log('Google translation failed: ' . $error -> getMessage()
                    . ' HTTP=' . $error -> httpStatus
                    . ' RPC=' . ($error -> rpcCode ?? 'none')
                    . ' cURL=' . $error -> transportCode
                    . '; cooldown=' . self::COOLDOWN_SECONDS . ' seconds');
                break;
            }
        }

        return $answers;
    }

    private function session(): array
    {
        $key = '';

        if (isset($this -> sessions[$key])) {
            return $this -> sessions[$key];
        }

        $response = $this -> request(self::HOME);
        self::checkStatus($response);
        $session = [];

        if (preg_match('/<script\b[^>]*>\s*window\.WIZ_global_data\s*=\s*(.*?)<\/script>/s', $response['body'], $script) !== 1) {
            throw new GoogleTranslationException('Google translation bootstrap data is missing.', 200);
        }

        foreach (['FdrFJe' => 'session', 'cfb2h' => 'build'] as $field => $name) {
            if (preg_match('/"' . $field . '"\s*:\s*("(?:[^"\\\\]|\\\\.)*"|-?[0-9]+)/', $script[1], $match) !== 1) {
                throw new GoogleTranslationException('Google translation bootstrap field is missing: ' . $name . '.', 200);
            }

            $value = json_decode($match[1], true);

            if ((!is_string($value) && !is_int($value)) || (string) $value === '') {
                throw new GoogleTranslationException('Google translation bootstrap field is invalid: ' . $name . '.', 200);
            }

            $session[$name] = (string) $value;
        }

        return $this -> sessions[$key] = $session;
    }

    private static function checkStatus(array $response): void
    {
        if ($response['status'] !== 200) {
            throw new GoogleTranslationException(
                'Google translation returned HTTP ' . $response['status'] . '.',
                $response['status'], self::retryAfter($response['retryAfter'])
            );
        }
    }

    /** @return array{status: int, body: string, retryAfter: ?string} */
    protected function request(string $url, ?string $form = null): array
    {
        $key = '';

        if (!isset($this -> handles[$key])) {
            $this -> handles[$key] = curl_init();
            curl_setopt($this -> handles[$key], CURLOPT_COOKIEFILE, '');
        }

        $handle = $this -> handles[$key];
        $body = '';
        $retry_after = null;
        $too_large = false;
        $max_bytes = $form === null ? self::MAX_HOME_BYTES : self::MAX_RESPONSE_BYTES;

        try {
            curl_setopt_array($handle, [
                CURLOPT_URL => $url,
                CURLOPT_POST => $form !== null,
                CURLOPT_HTTPHEADER => $form === null ? [] : [
                    'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                    'Origin: https://translate.google.com',
                    'Referer: https://translate.google.com/',
                ],
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => min(3, $this -> patience),
                CURLOPT_TIMEOUT => $this -> patience,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                CURLOPT_ENCODING => '',
                CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$retry_after): int {
                    if (stripos($header, 'HTTP/') === 0) {
                        $retry_after = null;
                    } elseif (stripos($header, 'Retry-After:') === 0) {
                        $retry_after = trim(substr($header, strlen('Retry-After:')));
                    }

                    return strlen($header);
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$too_large, $max_bytes): int {
                    if (strlen($body) + strlen($chunk) > $max_bytes) {
                        $too_large = true;

                        return 0;
                    }

                    $body .= $chunk;

                    return strlen($chunk);
                },
            ]);

            if ($form !== null) {
                curl_setopt($handle, CURLOPT_POSTFIELDS, $form);
            }

            $ok = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

            if ($ok === false) {
                throw new GoogleTranslationException(
                    $too_large ? 'Google translation response exceeded the size limit.' : 'Google translation transport failed.',
                    $status, self::retryAfter($retry_after), curl_errno($handle)
                );
            }

            return ['status' => $status, 'body' => $body, 'retryAfter' => $retry_after];
        } finally {
            // Release callback captures, especially the multi-megabyte homepage.
            curl_setopt($handle, CURLOPT_WRITEFUNCTION, static fn ($handle, string $chunk): int => strlen($chunk));
            curl_setopt($handle, CURLOPT_HEADERFUNCTION, static fn ($handle, string $header): int => strlen($header));
        }
    }

    public function __destruct()
    {
        foreach ($this -> handles as $handle) {
            curl_close($handle);
        }
    }

    protected function acquireTranslationLock(): bool
    {
        try {
            RateLimiter::acquireLock('google-translation-inflight:' . Config::get('database'), 0);

            return true;
        } catch (RateLimitLockException) {
            return false;
        }
    }

    protected function releaseTranslationLock(): void
    {
        RateLimiter::releaseLock('google-translation-inflight:' . Config::get('database'));
    }

    protected function coolingDown(): bool
    {
        $key = 'google-translation-failure';

        try {
            $blocked = RateLimiter::tooManyAttempts($key, 1, self::COOLDOWN_SECONDS, 0);
        } catch (RateLimitLockException) {
            return true;
        }

        if (!$blocked) {
            RateLimiter::releaseLock($key);
        }

        return $blocked;
    }

    protected function coolDown(): void
    {
        RateLimiter::recordAttempt('google-translation-failure');
    }

    private static function retryAfter(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) min((float) $value, PHP_INT_MAX);
        }

        $date = strtotime($value);

        return $date === false ? null : max(0, $date - time());
    }

    private function answer(string $body): string
    {
        if (!str_starts_with($body, ")]}'")) {
            throw new \GoogleTranslationException('Google translation returned an invalid RPC envelope.', 200);
        }

        $remaining = substr($body, 4);
        $payload = null;

        while (trim($remaining) !== '') {
            $remaining = ltrim($remaining, "\r\n ");

            if (preg_match('/^([0-9]+)\r?\n([^\r\n]+)\r?\n/', $remaining, $match) !== 1) {
                throw new GoogleTranslationException('Google translation returned invalid RPC framing.', 200);
            }

            $length = (int) $match[1];
            // The complete JSON record defines its boundary. Google's framing
            // counter must not be interpreted as a PHP byte count for Unicode.
            if ($length < 1 || $length > self::MAX_RESPONSE_BYTES) {
                throw new GoogleTranslationException('Google translation returned an invalid RPC frame length.', 200);
            }

            try {
                $records = json_decode($match[2], true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new GoogleTranslationException('Google translation returned invalid RPC JSON.', 200);
            }

            $remaining = substr($remaining, strlen($match[0]));

            if (!is_array($records) || !array_is_list($records)) {
                throw new GoogleTranslationException('Google translation returned invalid RPC records.', 200);
            }

            foreach ($records as $record) {
                if (!is_array($record) || ($record[1] ?? null) !== self::RPC) {
                    continue;
                }

                if (($record[0] ?? null) === 'er') {
                    throw new GoogleTranslationException('Google translation RPC refused the request.', 200, null, 0,
                        is_int($record[2] ?? null) ? $record[2] : null);
                }

                if (($record[0] ?? null) === 'wrb.fr' && is_int($record[5][0] ?? null)) {
                    throw new GoogleTranslationException('Google translation RPC refused the request.', 200, null, 0, $record[5][0]);
                }

                if (($record[0] ?? null) !== 'wrb.fr' || !is_string($record[2] ?? null) || $payload !== null) {
                    throw new GoogleTranslationException('Google translation returned an invalid RPC result.', 200);
                }

                $payload = $record[2];
            }
        }

        try {
            $data = $payload === null ? null : json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new GoogleTranslationException('Google translation returned invalid translation JSON.', 200);
        }

        $paragraphs = $data[1][0] ?? null;

        if (!is_array($paragraphs) || !array_is_list($paragraphs) || $paragraphs === []) {
            throw new GoogleTranslationException('Google translation returned no translated text.', 200);
        }

        $answer = [];

        foreach ($paragraphs as $paragraph) {
            $segments = $paragraph[5] ?? null;

            if (!is_array($segments) || !array_is_list($segments) || $segments === []) {
                throw new GoogleTranslationException('Google translation returned invalid translation segments.', 200);
            }

            $parts = [];

            foreach ($segments as $segment) {
                if (!is_array($segment) || !is_string($segment[0] ?? null)) {
                    throw new GoogleTranslationException('Google translation returned an invalid translation segment.', 200);
                }

                $parts[] = $segment[0];
            }

            $answer[] = implode(' ', $parts);
        }

        $text = implode("\n", $answer);

        if (trim($text) === '') {
            throw new GoogleTranslationException('Google translation returned no translated text.', 200);
        }

        return $text;
    }
}
