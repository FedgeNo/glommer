<?php

declare(strict_types=1);

class WebSocketPushRequestTest extends TestCase
{
    private const SECRET = 'test-secret';

    private function request(mixed $user_id): ?WebSocketPushRequest
    {
        $body = base64_encode(json_encode([
            'userId' => $user_id,
            'payload' => ['type' => 'test'],
            'issuedAt' => time(), 'nonce' => bin2hex(random_bytes(16)),
        ]));

        return WebSocketPushRequest::fromJSON(json_encode([
            'push' => $body,
            'signature' => hash_hmac('sha256', 'glommer.ws.push.v1.' . $body, self::SECRET),
        ]), self::SECRET);
    }

    public function testAWholeNumberUserIdIsAccepted(): void
    {
        $this -> assertSame(12, $this -> request(12) ?-> userId);
        $this -> assertSame(12, $this -> request('12') ?-> userId);
    }

    public function testAnythingThatIsNotAWholeNumberIsRefused(): void
    {
        $this -> assertNull($this -> request(1.5));
        $this -> assertNull($this -> request('1.5'));
        $this -> assertNull($this -> request('twelve'));
        $this -> assertNull($this -> request([12]));
    }

    public function testPushesAreSignedWithoutSendingTheKeyAndCannotBeReplayedOrRepurposed(): void
    {
        Env::get('WS_SECRET');
        $original = getenv('WS_SECRET');
        putenv('WS_SECRET=' . self::SECRET);
        Config::reload();
        try {
            $line = WebSocketPushRequest::encode(12, ['type' => 'test', 'text' => 'hello']);
            $envelope = json_decode($line, true);
            $this -> assertFalse(str_contains($line, self::SECRET));
            $this -> assertFalse(str_contains(base64_decode($envelope['push']), self::SECRET));
            $this -> assertNull(WebSocketPushRequest::fromJSON($line, 'wrong-key'));
            $this -> assertNull(WebSocketPushRequest::fromJSON($line, null));
            $this -> assertNull(WebSocketPushRequest::fromJSON($line, ''));
            $this -> assertNull((new WSRevocation()) -> accept(json_encode([
                'control' => $envelope['push'], 'signature' => $envelope['signature'],
            ]), self::SECRET));
            $push = WebSocketPushRequest::fromJSON($line, self::SECRET);
            $this -> assertSame(12, $push ?-> userId);
            $this -> assertSame('hello', $push -> payload['text']);
            $this -> assertNull(WebSocketPushRequest::fromJSON($line, self::SECRET));

            $body = json_decode(base64_decode($envelope['push']), true);
            $body['userId'] = 99;
            $envelope['push'] = base64_encode(json_encode($body));
            $this -> assertNull(WebSocketPushRequest::fromJSON(json_encode($envelope), self::SECRET));
            $body['issuedAt'] = time() - 30;
            $envelope['push'] = base64_encode(json_encode($body));
            $envelope['signature'] = hash_hmac('sha256', 'glommer.ws.push.v1.' . $envelope['push'], self::SECRET);
            $this -> assertNull(WebSocketPushRequest::fromJSON(json_encode($envelope), self::SECRET));
            $this -> assertNull(WebSocketPushRequest::fromJSON(json_encode([
                'secret' => self::SECRET, 'userId' => 12, 'payload' => [],
            ]), self::SECRET), 'the obsolete raw-secret protocol is not a fallback');
        } finally {
            putenv($original === false ? 'WS_SECRET' : 'WS_SECRET=' . $original);
            Config::reload();
        }
    }
}
