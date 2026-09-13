<?php

declare(strict_types=1);

class APIRequest
{
    public const MAX_JSON_BODY_BYTES = 1048576;

    /** Read an ordinary JSON object and check its endpoint's field types. */
    public static function read(array $fields): array
    {
        try {
            $body = file_get_contents('php://input', false, null, 0, self::MAX_JSON_BODY_BYTES + 1);
            if ($body === false) throw new \InvalidArgumentException('Cannot read the request body.');
            return self::decode($body, $fields);
        } catch (\InvalidArgumentException $exception) {
            JSONResponse::localizedError('malformedRequest', 422) -> send();
        }
    }

    /** Missing/null fields keep their existing endpoint defaults and required checks. */
    public static function decode(string $body, array $fields): array
    {
        if (self::JSONBodyTooLarge(null, $body)) {
            throw new \InvalidArgumentException('Request body is too large.');
        }
        try {
            $object = trim($body) === '' ? new \stdClass() : json_decode($body, false, 512, JSON_THROW_ON_ERROR);
            if (!$object instanceof \stdClass) {
                throw new \InvalidArgumentException('Expected a JSON object.');
            }
            // JSON numbers can overflow into INF while decoding. Reject them
            // even in opaque/unused fields before a later encoder sees them.
            $json = json_encode($object, JSON_THROW_ON_ERROR);
            self::checkFields($object, $fields);
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Malformed JSON.', 0, $exception);
        }
    }

    private static function checkFields(object $object, array $fields): void
    {
        foreach ($fields as $name => $type) {
            if (!isset($object -> {$name})) {
                continue;
            }
            $value = $object -> {$name};
            if (is_array($type)) {
                if ($value === []) continue;
                if (!$value instanceof \stdClass) {
                    throw new \InvalidArgumentException('Expected an object field.');
                }
                self::checkFields($value, $type);
                continue;
            }
            $valid = match ($type) {
                'text' => is_string($value),
                'integer' => self::integer($value),
                'boolean' => is_bool($value),
                'number' => self::number($value),
                'optional-number' => $value === '' || self::number($value),
                'integer-list' => self::integerList($value),
                'text-map' => ($value instanceof \stdClass || $value === [])
                    && self::textMap($value),
                // Call signalling intentionally carries opaque browser JSON.
                'json' => true,
                default => throw new \LogicException('Unknown API field type: ' . $type),
            };
            if (!$valid) {
                throw new \InvalidArgumentException('Invalid field type: ' . $name);
            }
        }
    }

    private static function integer(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false);
    }

    private static function number(mixed $value): bool
    {
        return (is_int($value) || is_float($value) || is_string($value))
            && is_numeric($value) && is_finite((float) $value);
    }

    private static function integerList(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) return false;
        foreach ($value as $item) {
            if (!self::integer($item)) return false;
        }
        return true;
    }

    private static function textMap(object|array $value): bool
    {
        foreach ($value as $key => $text) {
            if (!self::integer($key) || (int) $key <= 0 || !is_string($text)) {
                return false;
            }
        }
        return true;
    }

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
