<?php

declare(strict_types=1);

/**
 * Everything a login request itself reveals about the client - IP, UA, and
 * every other passively-visible signal (Client Hints, Sec-Fetch-*, Accept-*,
 * negotiated TLS cipher/protocol) - captured once per login/re-login and
 * tied to the account, never surfaced back to the user. Purely passive: no
 * client-side collection script, only headers and connection info already
 * present on the request.
 */
class LoginFingerprint
{
    public static function record(int $user_id): void
    {
        DB::run('
INSERT INTO `LoginFingerprints` (`userId`, `ipAddress`, `userAgent`, `acceptLanguage`, `acceptEncoding`, `referer`, `secChUa`, `secChUaMobile`, `secChUaPlatform`, `secFetchSite`, `secFetchMode`, `secFetchDest`, `secFetchUser`, `dnt`, `httpProtocol`, `tlsCipher`, `tlsProtocol`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
', 'issssssssssssssss', $user_id,
            ServerURL::clientIP(),
            self::header('HTTP_USER_AGENT', 255),
            self::header('HTTP_ACCEPT_LANGUAGE', 255),
            self::header('HTTP_ACCEPT_ENCODING', 255),
            self::header('HTTP_REFERER', 255),
            self::header('HTTP_SEC_CH_UA', 255),
            self::header('HTTP_SEC_CH_UA_MOBILE', 8),
            self::header('HTTP_SEC_CH_UA_PLATFORM', 64),
            self::header('HTTP_SEC_FETCH_SITE', 32),
            self::header('HTTP_SEC_FETCH_MODE', 32),
            self::header('HTTP_SEC_FETCH_DEST', 32),
            self::header('HTTP_SEC_FETCH_USER', 16),
            self::header('HTTP_DNT', 8),
            self::header('SERVER_PROTOCOL', 16),
            self::header('SSL_CIPHER', 64),
            self::header('SSL_PROTOCOL', 16)
        );
    }

    private static function header(string $key, int $max_length): ?string
    {
        $value = $_SERVER[$key] ?? null;

        return is_string($value) && $value !== '' ? substr($value, 0, $max_length) : null;
    }
}
