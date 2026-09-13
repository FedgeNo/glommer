<?php

declare(strict_types=1);

/** PHP request-side authorization. The WebSocket daemon never calls this class. */
class WebSocketAuthentication
{
    public static function token(): string
    {
        // Start before reading credentials: a stale read must not mint a lease
        // that outlives the daemon's revocation record.
        $issued_at = time();
        $user = Auth::id() === null ? null : User::load((int) Auth::id());

        if ($user === null || $user -> banned || $user -> sessionVersion !== ($_SESSION['sessionVersion'] ?? 0)) {
            return '';
        }

        if (!array_key_exists('wsRememberSelector', $_SESSION)) {
            $_SESSION['wsRememberSelector'] = RememberToken::authenticatedSelector((int) $user -> userId);
        }

        $selector = $_SESSION['wsRememberSelector'];
        $device_id = null;

        if ($selector !== null) {
            // Written after successful login/token issuance or full cookie
            // validation above, never trusted from a supplied selector alone.
            $device = DB::row('
SELECT `tokenId`
    FROM `RememberTokens`
    WHERE `userId` = ? AND `selector` = ? AND `consumedAt` IS NULL AND `expiresAt` > NOW()
', 'RememberTokenData', 'is', $user -> userId, $selector);

            if ($device === null) {
                return '';
            }

            $device_id = self::deviceId($selector);
        }

        if (!WSToken::isIdentity($_SESSION['wsSessionId'] ?? null)) {
            $_SESSION['wsSessionId'] = bin2hex(random_bytes(32));
        }

        return WSToken::issue((int) $user -> userId, $user -> sessionVersion, $_SESSION['wsSessionId'], $device_id, $issued_at);
    }

    public static function deviceId(string $selector): string
    {
        return hash('sha256', 'glommer.ws.device.v1.' . $selector);
    }

    public static function revokeCurrent(): void
    {
        $user_id = Auth::id();
        $session_id = $_SESSION['wsSessionId'] ?? null;

        if ($user_id !== null && WSToken::isIdentity($session_id)) {
            WebSocketPusher::revoke($user_id, 'session', $session_id);
        }
    }
}
