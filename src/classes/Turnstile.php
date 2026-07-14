<?php

declare(strict_types=1);

/**
 * Cloudflare Turnstile ("I am not a robot") integration. Optional: enabled only
 * once an admin has set both keys (in setup or the admin panel), and a no-op
 * otherwise. The site key renders the widget in the browser; the secret key
 * verifies the returned token server-side against Cloudflare, so the check
 * can't be skipped by posting straight to the endpoint.
 */
class Turnstile
{
    public const SITE_KEY_SETTING = 'turnstileSiteKey';
    public const SECRET_KEY_SETTING = 'turnstileSecretKey';

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const CONNECT_TIMEOUT_SECONDS = 3;
    private const TOTAL_TIMEOUT_SECONDS = 5;

    public static function siteKey(): string
    {
        return (string) Settings::get(self::SITE_KEY_SETTING, '');
    }

    private static function secretKey(): string
    {
        return (string) Settings::get(self::SECRET_KEY_SETTING, '');
    }

    /**
     * Whether the CAPTCHA is configured, and so should be shown and enforced.
     * Both keys are required - the site key to render the widget, the secret key
     * to verify it - so one without the other stays off.
     */
    public static function isEnabled(): bool
    {
        return self::siteKey() !== '' && self::secretKey() !== '';
    }

    /**
     * Verifies a Turnstile token against Cloudflare's siteverify endpoint.
     * Passes when the CAPTCHA isn't configured (nothing to enforce). A missing
     * token, or an explicit failure from Cloudflare, always fails. When
     * Cloudflare itself can't be reached (timeout/network error - distinct from a
     * definite "invalid" verdict) the outcome is $fail_open_on_error: sign-up
     * passes it false (fail closed - an outage mustn't open a bot window), while
     * sign-in passes it true (fail open - a Cloudflare outage mustn't lock every
     * existing user out of an account they can already prove with a password).
     */
    public static function verify(?string $token, ?string $remote_ip, bool $fail_open_on_error = false): bool
    {
        if (!self::isEnabled()) {
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        $fields = [
            'secret' => self::secretKey(),
            'response' => $token,
        ];

        if ($remote_ip !== null && $remote_ip !== '') {
            $fields['remoteip'] = $remote_ip;
        }

        // A fixed, trusted Cloudflare endpoint (not a user-supplied URL), so a
        // plain curl POST - not the SSRF-guarded SafeHTTPFetcher, which is for
        // fetching untrusted user links.
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => self::VERIFY_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // Couldn't reach Cloudflare - indeterminate, not a definite rejection.
        if ($body === false || $status < 200 || $status >= 300) {
            return $fail_open_on_error;
        }

        $data = json_decode((string) $body, true);

        if (!is_array($data) || !array_key_exists('success', $data)) {
            return $fail_open_on_error;
        }

        if ($data['success'] !== true) {
            error_log('Turnstile siteverify rejected a token: ' . json_encode($data['error-codes'] ?? $data));
        }

        return $data['success'] === true;
    }
}
