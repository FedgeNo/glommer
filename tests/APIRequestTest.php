<?php

declare(strict_types=1);

class APIRequestTest extends TestCase
{
    private function rejects(string $body, array $fields): void
    {
        try {
            APIRequest::decode($body, $fields);
        } catch (\InvalidArgumentException $exception) {
            return;
        }
        $this -> assertTrue(false, 'Malformed request was accepted: ' . $body);
    }

    public function testMalformedBodiesNeverBecomeAnEmptySuccessfulRequest(): void
    {
        foreach (['null', 'false', '5', '"text"', '[]', '[{}]', '{', '{"unused":1e999}'] as $body) {
            $this -> rejects($body, []);
        }
        $this -> assertSame([], APIRequest::decode('', []));
        $this -> assertSame([], APIRequest::decode('{}', []));
        $this -> rejects('{"text":"' . str_repeat('a', APIRequest::MAX_JSON_BODY_BYTES) . '"}', ['text' => 'text']);
    }

    public function testTextFieldsRejectCoercionBeforeEndpointStringOperations(): void
    {
        foreach (['[]', '{}', 'true', '123', '1.5'] as $value) {
            $this -> rejects('{"title":' . $value . '}', ['title' => 'text']);
        }
        $this -> assertSame(['title' => '0'], APIRequest::decode('{"title":"0"}', ['title' => 'text']));
        $this -> assertSame(['title' => null], APIRequest::decode('{"title":null}', ['title' => 'text']));
        $this -> assertSame([], APIRequest::decode('{}', ['title' => 'text']));
    }

    public function testIDsFlagsAndCoordinatesHaveDistinctTypes(): void
    {
        $fields = ['postId' => 'integer', 'sensitive' => 'boolean', 'latitude' => 'optional-number'];
        $body = '{"postId":"42","sensitive":false,"latitude":"49.25"}';
        $this -> assertSame(json_decode($body, true), APIRequest::decode($body, $fields));
        foreach (['{"postId":true}', '{"postId":1.5}', '{"postId":"999999999999999999999999"}',
            '{"postId":[]}', '{"sensitive":"false"}', '{"sensitive":0}', '{"latitude":true}',
            '{"latitude":[]}', '{"latitude":"INF"}', '{"latitude":"1e999"}'] as $invalid) {
            $this -> rejects($invalid, $fields);
        }
        $this -> assertSame(['latitude' => ''], APIRequest::decode('{"latitude":""}', $fields));
    }

    public function testStructuredBrowserPayloadsRetainTheirArraysAndKeys(): void
    {
        $fields = [
            'optionIds' => 'integer-list', 'altTexts' => 'text-map',
            'cursor' => ['postId' => 'integer', 'sortAt' => 'text'],
            'publicKey' => ['kty' => 'text', 'crv' => 'text', 'x' => 'text', 'y' => 'text'],
            'wrappedPrivateKey' => ['salt' => 'text', 'iterations' => 'integer', 'iv' => 'text', 'ciphertext' => 'text'],
            'signal' => 'json',
        ];
        $body = '{"optionIds":[1,"2"],"altTexts":{"41":"A caption","42":""},'
            . '"cursor":{"postId":42,"sortAt":"2026-09-13 10:00:00"},'
            . '"publicKey":{"kty":"EC","crv":"P-256","x":"key-x","y":"key-y"},'
            . '"wrappedPrivateKey":{"salt":"salt","iterations":600000,"iv":"iv","ciphertext":"cipher"},'
            . '"signal":{"sdp":"opaque","extensions":[1,true,null]}}';
        $this -> assertSame(json_decode($body, true), APIRequest::decode($body, $fields));
        $this -> assertSame(['altTexts' => [], 'cursor' => [], 'optionIds' => []],
            APIRequest::decode('{"altTexts":{},"cursor":[],"optionIds":[]}', $fields));
        foreach (['{"optionIds":{}}', '{"optionIds":[true]}', '{"optionIds":[[1]]}',
            '{"altTexts":["caption"]}', '{"altTexts":{"1":[]}}', '{"altTexts":{"wrong":"caption"}}',
            '{"cursor":{"postId":[]}}', '{"cursor":"text"}', '{"publicKey":{"x":[]}}',
            '{"wrappedPrivateKey":{"iterations":true}}', '{"wrappedPrivateKey":{"ciphertext":{}}}'] as $invalid) {
            $this -> rejects($invalid, $fields);
        }
    }

    public function testTheJSONBodyCeilingAcceptsItsBoundary(): void
    {
        $this -> assertFalse(APIRequest::JSONBodyTooLarge(APIRequest::MAX_JSON_BODY_BYTES, ''));
        $this -> assertFalse(APIRequest::JSONBodyTooLarge(null, str_repeat('x', APIRequest::MAX_JSON_BODY_BYTES)));
    }

    public function testTheJSONBodyCeilingRefusesDeclaredAndActualExcess(): void
    {
        $this -> assertTrue(APIRequest::JSONBodyTooLarge(APIRequest::MAX_JSON_BODY_BYTES + 1, ''));
        $this -> assertTrue(APIRequest::JSONBodyTooLarge(null, str_repeat('x', APIRequest::MAX_JSON_BODY_BYTES + 1)));
    }
}
