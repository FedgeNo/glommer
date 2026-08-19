<?php

declare(strict_types=1);

class APIRequestTest extends TestCase
{
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
