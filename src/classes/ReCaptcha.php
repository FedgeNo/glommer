<?php

declare(strict_types=1);

/**
 * Google reCAPTCHA v2 ("I'm not a robot") integration, mirroring Turnstile.
 * Optional: enabled only once an admin has set both keys, a no-op otherwise.
 *
 * Unlike Turnstile - which, when configured, gates every sign-up and sign-in -
 * reCAPTCHA here is used in one narrow place: the recovery path for an account
 * that has hit its per-account login lockout (see api/login.php). It's a
 * separate provider on purpose - Google is blocked in a number of countries, so
 * it can't be the everyday CAPTCHA, but it's a widely-recognised challenge to
 * gate a locked account behind rather than hard-blocking every login for it
 * (which lets anyone lock a known account out for the window with a few wrong
 * passwords). When it isn't configured the lockout stays a hard block - the
 * account is never left open to unthrottled guessing just because no challenge
 * is set up.
 */
class ReCaptcha
{
    public const SITE_KEY_SETTING = 'recaptchaSiteKey';
    public const SECRET_KEY_SETTING = 'recaptchaSecretKey';

    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
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
     * Whether reCAPTCHA is configured, and so can gate a locked account. Both
     * keys are required - the site key to render the widget, the secret key to
     * verify it - so one without the other stays off.
     */
    public static function isEnabled(): bool
    {
        return self::siteKey() !== '' && self::secretKey() !== '';
    }

    /**
     * Verifies a reCAPTCHA token against Google's siteverify endpoint. Passes
     * when reCAPTCHA isn't configured (nothing to enforce). A missing token, or
     * an explicit failure from Google, always fails. When Google itself can't be
     * reached (timeout/network error - distinct from a definite "invalid"
     * verdict) the outcome is $fail_open_on_error.
     *
     * The lockout caller leaves this at its fail-closed default: a challenge
     * that can't be reached mustn't wave through the very guessing the lockout
     * exists to stop - the account simply stays locked until the window passes.
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

        // A fixed, trusted Google endpoint (not a user-supplied URL), so a plain
        // curl POST - not the SSRF-guarded SafeHTTPFetcher, which is for
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

        // Couldn't reach Google - indeterminate, not a definite rejection.
        if ($body === false || $status < 200 || $status >= 300) {
            return $fail_open_on_error;
        }

        $data = json_decode((string) $body, true);

        if (!is_array($data) || !array_key_exists('success', $data)) {
            return $fail_open_on_error;
        }

        if ($data['success'] !== true) {
            error_log('reCAPTCHA siteverify rejected a token: ' . json_encode($data['error-codes'] ?? $data));
        }

        return $data['success'] === true;
    }
}
