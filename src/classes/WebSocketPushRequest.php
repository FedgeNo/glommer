<?php

declare(strict_types=1);

class WebSocketPushRequest
{
    public int $userId;
    public mixed $payload;

    public static function fromJSON(string $json, ?string $expected_secret): ?self
    {
        $request = json_decode($json, true);

        if (
            !is_array($request)
            || !isset($request['secret'], $request['userId'], $request['payload'])
            || !is_string($request['secret'])
            || $expected_secret === null
            || $expected_secret === ''
            || !hash_equals($expected_secret, $request['secret'])
            || !(is_int($request['userId']) || (is_string($request['userId']) && ctype_digit($request['userId'])))
        ) {
            return null;
        }

        $push = new self();
        $push -> userId = (int) $request['userId'];
        $push -> payload = $request['payload'];

        return $push;
    }
}
