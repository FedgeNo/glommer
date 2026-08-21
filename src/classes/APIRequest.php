<?php

declare(strict_types=1);

class APIRequest
{
    public const MAX_JSON_BODY_BYTES = 1048576;

    /** Refuses oversized ordinary API JSON without affecting multipart uploads. */
    public static function guardJSONBodySize(): void
    {
        $content_type = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));

        if (preg_match('/^application\/(?:[a-z0-9.+-]+\+)?json$/', $content_type) !== 1) {
            return;
        }

        $content_length = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);

        if (self::JSONBodyTooLarge(is_int($content_length) ? $content_length : null, '')) {
            JSONResponse::localizedError('requestBodyTooLarge', 413) -> send();
        }

        $body = file_get_contents('php://input', false, null, 0, self::MAX_JSON_BODY_BYTES + 1);

        if ($body !== false && self::JSONBodyTooLarge(null, $body)) {
            JSONResponse::localizedError('requestBodyTooLarge', 413) -> send();
        }
    }

    public static function JSONBodyTooLarge(?int $content_length, string $body): bool
    {
        return ($content_length !== null && $content_length > self::MAX_JSON_BODY_BYTES)
            || strlen($body) > self::MAX_JSON_BODY_BYTES;
    }
}
