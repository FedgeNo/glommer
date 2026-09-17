<?php

declare(strict_types=1);

final class GoogleTranslationTest extends TestCase {
    private function translator(array $responses): GoogleTranslation {
        return new class ($responses) extends GoogleTranslation {
            public array $urls = [];
            public array $forms = [];
            public array $homes = [];
            public bool $blocked = false;
            public bool $lockAvailable = true;
            public int $releases = 0;
            public array $homeResponse = ['body' => '<script>window.WIZ_global_data = {"FdrFJe":"test-session","cfb2h":"test-build"};</script>', 'status' => 200, 'retryAfter' => null];

            public function __construct(private array $responses) {
                parent::__construct(from: 'en', to: 'es', patience: 5);
            }

            protected function coolingDown(): bool {
                return $this -> blocked;
            }

            protected function acquireTranslationLock(): bool {
                return $this -> lockAvailable;
            }

            protected function releaseTranslationLock(): void {
                $this -> releases++;
            }

            protected function coolDown(): void {
                $this -> blocked = true;
            }

            protected function request(string $url, ?string $form = null): array {
                if ($form === null) {
                    $this -> homes[] = $url;

                    return $this -> homeResponse;
                }

                $this -> forms[] = $form;
                $this -> urls[] = $url;
                $response = array_shift($this -> responses);

                if ($response instanceof GoogleTranslationException) {
                    throw $response;
                }

                return $response;
            }
        };
    }

    private function response(string $body, int $status = 200, ?string $retry_after = null): array {
        return ['body' => $body, 'status' => $status, 'retryAfter' => $retry_after];
    }

    private function frame(array $records): string {
        $json = json_encode($records, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return ")]}'\n\n" . (strlen($json) + 2) . "\n" . $json . "\n";
    }

    private function translated(string ...$texts): array {
        $segments = array_map(static fn (string $text): array => [$text, null, null, null, null, null, 'source', 1], $texts);
        $data = [null, [[[null, null, null, null, null, $segments]]]];

        return $this -> response($this -> frame([['wrb.fr', 'MkEWBc', json_encode($data, JSON_UNESCAPED_UNICODE)]]));
    }

    public function testTranslatesSegmentsAndPreservesKeys(): void {
        $translator = $this -> translator([$this -> translated('Hola', 'mundo.')]);
        $this -> assertSame(['title' => 'Hola mundo.'], $translator -> translate(['title' => 'Hello world.']));
        $this -> assertTrue($translator -> isAvailable());
        $this -> assertNull($translator -> error());
    }

    public function testRequestEncodesTextAndUsesBootstrapData(): void {
        $translator = $this -> translator([$this -> translated('Hola')]);
        $translator -> translate(['A & B? + café']);
        parse_str((string) parse_url($translator -> urls[0], PHP_URL_QUERY), $params);
        $this -> assertSame('MkEWBc', $params['rpcids']);
        // parse_str normalizes periods in PHP array keys.
        $this -> assertSame('test-session', $params['f_sid']);
        $this -> assertSame('test-build', $params['bl']);
        parse_str($translator -> forms[0], $form);
        $calls = json_decode($form['f_req'], true);
        $payload = json_decode($calls[0][0][1], true);
        $this -> assertSame(['A & B? + café', 'en', 'es', 1, null, 2], $payload[0]);
    }

    public function testHTTPFailuresStopRequestsAndExposeRetryAfter(): void {
        foreach ([400, 403, 429, 500, 503, 302] as $status) {
            $translator = $this -> translator([
                $this -> translated('Hola'),
                $this -> response('<html>Refused</html>', $status, '120'),
            ]);
            $this -> assertSame([2 => 'Hola', 7 => '', 8 => ''], $translator -> translate([2 => 'Hello', 7 => 'Goodbye', 8 => 'Evening']));
            $this -> assertFalse($translator -> isAvailable());
            $this -> assertSame($status, $translator -> error() -> httpStatus);
            $this -> assertSame(120, $translator -> error() -> retryAfterSeconds);
            $this -> assertSame([''], $translator -> translate(['Another request']));
            $this -> assertSame(2, count($translator -> urls));
        }
    }

    public function testMalformedOrEmptySuccessBodiesAreFailures(): void {
        foreach (['<html>captcha</html>', '{"error":{"code":400}}', 'null', '[]', '[[]]', '[[[123]]]', '[[[""]]]', '[[["Hola"],false]]', '{broken'] as $body) {
            $translator = $this -> translator([$this -> response($body)]);
            $this -> assertSame([''], $translator -> translate(['Hello']));
            $this -> assertFalse($translator -> isAvailable());
            $this -> assertSame(200, $translator -> error() -> httpStatus);
        }
    }

    public function testSessionIsReusedAndRPCFailuresAreNotHTTPSuccesses(): void {
        $error = $this -> frame([['wrb.fr', 'MkEWBc', null, null, null, [3], 'generic']]);
        $translator = $this -> translator([$this -> translated('Hola'), $this -> response($error)]);
        $this -> assertSame(['Hola'], $translator -> translate(['Hello']));
        $this -> assertSame([''], $translator -> translate(['Goodbye']));
        $this -> assertSame(1, count($translator -> homes));
        $this -> assertSame(3, $translator -> error() -> rpcCode);
        $this -> assertSame(200, $translator -> error() -> httpStatus);
        $this -> assertFalse($translator -> isAvailable());
    }

    public function testUnchangedProperNamesAreNotRPCFailures(): void {
        $data = [null, [[[null, null, null, null, null, [['Google']], 'en']]]];
        $translator = $this -> translator([$this -> response($this -> frame([['wrb.fr', 'MkEWBc', json_encode($data)]]))]);
        $this -> assertSame(['Google'], $translator -> translate(['Google']));
        $this -> assertTrue($translator -> isAvailable());
    }

    public function testUnicodeFrameCountersDoNotSplitJSONRecords(): void {
        $response = $this -> translated('こんにちは世界');
        $lines = explode("\n", $response['body']);
        $lines[2] = (string) (mb_strlen($lines[3], 'UTF-8') + 2);
        $response['body'] = implode("\n", $lines);
        $translator = $this -> translator([$response]);
        $this -> assertSame(['こんにちは世界'], $translator -> translate(['Hello world']));
        $this -> assertTrue($translator -> isAvailable());
    }

    public function testInvalidSegmentsAndDuplicateResultsAreRejected(): void {
        $data = [null, [[[null, null, null, null, null, [['Hola'], [123]]]]]];
        $record = ['wrb.fr', 'MkEWBc', json_encode($data)];

        foreach ([[$record], [$record, $record]] as $records) {
            $translator = $this -> translator([$this -> response($this -> frame($records))]);
            $this -> assertSame([''], $translator -> translate(['Hello']));
            $this -> assertFalse($translator -> isAvailable());
        }
    }

    public function testBootstrapFailuresPreventTranslationRequests(): void {
        foreach (['<html>Consent required</html>', '<script>window.WIZ_global_data = {"FdrFJe":"test"};</script>'] as $body) {
            $translator = $this -> translator([]);
            $translator -> homeResponse = $this -> response($body);
            $this -> assertSame([''], $translator -> translate(['Hello']));
            $this -> assertFalse($translator -> isAvailable());
            $this -> assertSame([], $translator -> urls);
        }
    }

    public function testMissingWrongOrTruncatedRPCResultsAreFailures(): void {
        foreach ([
            $this -> frame([['wrb.fr', 'different-method', '[]']]),
            $this -> frame([['wrb.fr', 'MkEWBc', null]]),
            $this -> frame([['wrb.fr', 'MkEWBc', '{broken']]),
            $this -> frame([['wrb.fr', 'MkEWBc', '[]']]),
            substr($this -> translated('Hola')['body'], 0, -10),
            $this -> translated('Hola')['body'] . 'bad trailing frame',
        ] as $body) {
            $translator = $this -> translator([$this -> response($body)]);
            $this -> assertSame([''], $translator -> translate(['Hello']));
            $this -> assertFalse($translator -> isAvailable());
        }
    }

    public function testTransportFailureIsInspectableAndNeverReturnsSourceAsSuccess(): void {
        $translator = $this -> translator([new GoogleTranslationException('Google translation transport failed.', 0, null, CURLE_OPERATION_TIMEDOUT)]);
        $this -> assertSame(['title' => ''], $translator -> translate(['title' => 'Hello']));
        $this -> assertFalse($translator -> isAvailable());
        $this -> assertSame(CURLE_OPERATION_TIMEDOUT, $translator -> error() -> transportCode);
    }

    public function testInvalidInputsAreRejectedBeforeAnyRequest(): void {
        foreach ([123, "\xff", str_repeat('a', 5001)] as $invalid) {
            $translator = $this -> translator([]);

            try {
                $translator -> translate(['Hello', $invalid]);
                $this -> assertTrue(false, 'invalid input must throw');
            } catch (\InvalidArgumentException) {
                $this -> assertSame([], $translator -> urls);
                $this -> assertTrue($translator -> isAvailable());
            }
        }
    }

    public function testEmptyInputMakesNoRequest(): void {
        $translator = $this -> translator([]);
        $this -> assertSame([], $translator -> translate([]));
        $this -> assertSame(['', ' '], $translator -> translate(['', ' ']));
        $this -> assertSame([], $translator -> urls);
    }

    public function testBusyReturnsImmediatelyWithoutStartingCooldown(): void {
        $translator = $this -> translator([$this -> translated('Hola')]);
        $translator -> lockAvailable = false;
        $this -> assertSame(['title' => ''], $translator -> translate(['title' => 'Hello']));
        $this -> assertFalse($translator -> isAvailable());
        $this -> assertFalse($translator -> blocked);
        $this -> assertSame([], $translator -> homes);
        $this -> assertSame(0, $translator -> releases);
        $translator -> lockAvailable = true;
        $this -> assertSame(['Hola'], $translator -> translate(['Hello']));
        $this -> assertSame(1, $translator -> releases);
    }

    public function testFailuresReleaseTheTranslationLock(): void {
        $translator = $this -> translator([$this -> response('Unavailable', 503)]);
        $this -> assertSame([''], $translator -> translate(['Hello']));
        $this -> assertSame(1, $translator -> releases);
        $this -> assertTrue($translator -> blocked);
    }

    public function testOnlyTheFailureStartingCooldownIsLogged(): void {
        $log = tempnam(sys_get_temp_dir(), 'google-translation-log-');
        $original_log = ini_get('error_log');

        try {
            ini_set('error_log', $log);
            $translator = $this -> translator([$this -> response('private response body', 429)]);
            $translator -> translate(['private submitted text']);
            $translator -> translate(['another private text']);
            $translator -> lockAvailable = false;
            $translator -> translate(['busy call']);
            $written = (string) file_get_contents($log);
            $this -> assertSame(1, substr_count($written, 'Google translation failed:'));
            $this -> assertTrue(str_contains($written, 'HTTP=429'));
            $this -> assertTrue(str_contains($written, 'cooldown=300 seconds'));
            $this -> assertFalse(str_contains($written, 'private'));
            $this -> assertFalse(str_contains($written, 'test-session'));
        } finally {
            ini_set('error_log', is_string($original_log) ? $original_log : '');
            unlink($log);
        }
    }

}
