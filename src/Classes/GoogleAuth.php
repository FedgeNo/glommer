<?php

declare(strict_types=1);

/**
 * "Continue with Google" sign-in (OAuth 2.0 / OpenID Connect, server-side
 * authorization-code flow). Optional: enabled only once an admin has set both
 * the client id and secret in the admin panel - stored in Settings, exactly
 * like the Turnstile keys, so no server access is needed to configure it.
 *
 * Accounts are keyed on the VERIFIED email Google returns (not a separate Google
 * id): the app is already email-unique, so this both dedupes cleanly and unifies
 * a Google sign-in with an existing email/password account of the same address.
 *
 * The id_token is fetched directly from Google's HTTPS token endpoint
 * (authenticated by the client secret over TLS), so its claims are validated
 * (aud / iss / exp / nonce) without re-verifying the RS256 signature - the
 * approach Google's own docs sanction for the code flow.
 */
class GoogleAuth
{
    public const CLIENT_ID_SETTING = 'googleAuthClientId';
    public const CLIENT_SECRET_SETTING = 'googleAuthSecret';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    public static function clientId(): string
    {
        return (string) Settings::get(self::CLIENT_ID_SETTING, '');
    }

    private static function clientSecret(): string
    {
        return (string) Settings::get(self::CLIENT_SECRET_SETTING, '');
    }

    /**
     * Whether Google sign-in is configured (and so should be shown/allowed).
     * Both halves are required - the id renders/authorizes, the secret exchanges
     * the code - so one without the other stays off.
     */
    public static function isEnabled(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }

    public static function redirectURI(): string
    {
        return ServerURL::absolute('/auth-google-callback');
    }

    /**
     * The Google consent-screen URL to send the browser to, carrying the CSRF
     * state and the replay-guard nonce (both echoed back and verified on return).
     */
    public static function authorizeURL(string $state, string $nonce): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => self::clientId(),
            'redirect_uri' => self::redirectURI(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Exchanges an authorization code for Google's id_token - a server-to-server
     * POST authenticated by the client secret. Returns the raw id_token, or null
     * on any failure.
     */
    public static function exchangeCodeForIdToken(string $code): ?string
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => self::TOKEN_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'code' => $code,
                'client_id' => self::clientId(),
                'client_secret' => self::clientSecret(),
                'redirect_uri' => self::redirectURI(),
                'grant_type' => 'authorization_code',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $status < 200 || $status >= 300) {
            return null;
        }

        $data = json_decode((string) $body, true);

        return is_array($data) && isset($data['id_token']) && is_string($data['id_token']) ? $data['id_token'] : null;
    }

    /**
     * Validates the id_token's claims (audience is our client, a Google issuer,
     * not expired, matching nonce, a verified email) and returns the profile, or
     * null. The token came straight from Google's HTTPS token endpoint, so the
     * signature is trusted by transport; only the claims are checked.
     *
     * @return array{email: string, name: ?string}|null
     */
    public static function verifiedProfile(string $id_token, string $expected_nonce): ?array
    {
        $parts = explode('.', $id_token);

        if (count($parts) !== 3) {
            return null;
        }

        // JWT segments are base64url with the padding stripped.
        $segment = strtr($parts[1], '-_', '+/');
        $segment .= str_repeat('=', (4 - strlen($segment) % 4) % 4);
        $payload = json_decode((string) base64_decode($segment, true), true);

        if (!is_array($payload)) {
            return null;
        }

        $email_verified = ($payload['email_verified'] ?? false);
        $email_verified = $email_verified === true || $email_verified === 'true';

        $email = $payload['email'] ?? null;

        if (($payload['aud'] ?? null) !== self::clientId()
            || !in_array((string) ($payload['iss'] ?? ''), self::ISSUERS, true)
            || (int) ($payload['exp'] ?? 0) < time()
            || $expected_nonce === '' || !is_string($payload['nonce'] ?? null) || !hash_equals($expected_nonce, (string) $payload['nonce'])
            || !$email_verified
            || !is_string($email) || $email === ''
        ) {
            return null;
        }

        return [
            'email' => $email,
            'name' => is_string($payload['name'] ?? null) ? $payload['name'] : null,
        ];
    }

    /**
     * Resolves the account for a verified Google email: the existing user of
     * that address (linking a Google sign-in to an email/password account for
     * free), or a freshly created, already-verified, passwordless-ish account
     * (a random unusable hash fills the NOT NULL column; the user can set a real
     * password later via forgot-password). Returns the User (which the caller
     * checks for a ban), or null when the email cannot be used (reserved for an
     * outstanding email-change revert).
     */
    public static function resolveUser(string $email, ?string $name): ?User
    {
        $mysqli = Database::connection();

        $stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Users`
    WHERE `email` = ?
');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_fetch_object(mysqli_stmt_get_result($stmt), User::class);

        if ($existing instanceof User) {
            // A verified account is the address's rightful owner, so just link.
            // An UNVERIFIED one never proved it owns this address - someone could
            // have pre-registered a victim's email and be lying in wait for the
            // victim's Google sign-in to inherit it. Google just proved the
            // address, so take the account over: burn the unknown password to a
            // fresh unusable hash, mark it verified, and kill every session and
            // remember-me token the pre-registration left behind (the same
            // revocation a password reset does).
            if (!$existing -> verified) {
                $existing_id = (int) $existing -> userId;
                $unusable_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                $verified = 1;

                $update_stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `passwordHash` = ?, `verified` = ?
    WHERE `userId` = ?
');
                mysqli_stmt_bind_param($update_stmt, 'sii', $unusable_hash, $verified, $existing_id);
                mysqli_stmt_execute($update_stmt);

                $existing -> passwordHash = $unusable_hash;
                $existing -> verified = $verified;
                $existing -> sessionVersion = User::bumpSessionVersion($existing_id);
                RememberToken::purgeForUser($existing_id);
            }

            return $existing;
        }

        // No account yet: refuse an email currently reserved for an outstanding
        // email-change revert, exactly as sign-up does - it belongs to whoever
        // is reclaiming it, not to a fresh Google account.
        if (EmailChangeRevert::isReserved($email)) {
            return null;
        }

        $username = self::generateUsername($email, $name);
        // An unguessable hash so the NOT NULL column is satisfied and no password
        // ever verifies - a Google account has no password until it sets one.
        $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $display_name = $name !== null && trim($name) !== '' ? mb_substr(trim($name), 0, 100) : null;
        $verified = 1;

        $stmt = mysqli_prepare($mysqli, '
INSERT INTO `Users` (`username`, `email`, `passwordHash`, `displayName`, `verified`)
    VALUES (?, ?, ?, ?, ?)
');
        mysqli_stmt_bind_param($stmt, 'ssssi', $username, $email, $hash, $display_name, $verified);
        mysqli_stmt_execute($stmt);

        $user = new User();
        $user -> userId = (int) mysqli_insert_id($mysqli);
        $user -> username = $username;
        $user -> email = $email;
        $user -> displayName = $display_name;
        $user -> verified = $verified;

        return $user;
    }

    /**
     * A unique username derived from the email local-part (then the display
     * name, then "user"), sanitised to the allowed charset and made unique by
     * appending digits.
     */
    private static function generateUsername(string $email, ?string $name): string
    {
        $sanitise = static fn (string $raw): string => (string) preg_replace('/[^a-z0-9_]/', '', strtolower($raw));

        $base = $sanitise(explode('@', $email)[0]);

        if ($base === '' && $name !== null) {
            $base = $sanitise($name);
        }

        if ($base === '') {
            $base = 'user';
        }

        $base = substr($base, 0, 24);
        $mysqli = Database::connection();
        $candidate = $base;

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $stmt = mysqli_prepare($mysqli, '
SELECT 1
    FROM `Users`
    WHERE `username` = ?
');
            mysqli_stmt_bind_param($stmt, 's', $candidate);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) === 0) {
                return $candidate;
            }

            $candidate = substr($base, 0, 20) . random_int(1000, 999999);
        }

        return substr($base, 0, 16) . bin2hex(random_bytes(6));
    }
}
