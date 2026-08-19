<?php

declare(strict_types=1);

class WebSocketPushRequestTest extends TestCase
{
    private const SECRET = 'test-secret';

    private function request(mixed $user_id): ?WebSocketPushRequest
    {
        return WebSocketPushRequest::fromJSON((string) json_encode([
            'secret' => self::SECRET,
            'userId' => $user_id,
            'payload' => ['type' => 'test'],
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
}
