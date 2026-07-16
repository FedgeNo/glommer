<?php

declare(strict_types=1);

/**
 * One row on the Settings "Remembered devices" list: a loosely-parsed
 * browser/OS label from the token's stored user-agent, its IP, when it was
 * first seen and last used, and a Revoke button - everything RememberToken
 * tracks per token except the selector/validator themselves, which never
 * leave the server.
 */
class RememberedDevice extends Div
{
    public ?string $class = 'Card d-flex align-items-center gap-3 RememberedDevice';

    public int $tokenId;
    public ?string $userAgent;
    public ?string $ipAddress;
    public string $createdAt;
    public string $lastUsedAt;
    public bool $isCurrent;

    public function __construct(RememberTokenData $row, bool $is_current)
    {
        parent::__construct();

        $this -> tokenId = (int) $row -> tokenId;
        $this -> userAgent = $row -> userAgent;
        $this -> ipAddress = $row -> ipAddress;
        $this -> createdAt = (string) $row -> createdAt;
        $this -> lastUsedAt = (string) $row -> lastUsedAt;
        $this -> isCurrent = $is_current;
    }

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-token-id'] = (string) $this -> tokenId;

        $info = new Div();
        $info -> class = 'd-flex flex-column gap-1';

        $label = self::describe($this -> userAgent);

        if ($this -> isCurrent) {
            $label .= ' (this device)';
        }

        $info -> addContent(new Paragraph($label));

        $detail_line = new Paragraph($this -> ipAddress !== null ? $this -> ipAddress . ' - ' : null);
        $detail_line -> class = 'Muted';
        $detail_line -> addContent('Last used ');
        $detail_line -> addContent(new RelativeTime($this -> lastUsedAt));
        $info -> addContent($detail_line);

        $this -> addContent($info);

        // The current device isn't revocable from its own row - revoking it
        // would log this very session's persistent cookie out from under the
        // user mid-visit; logout is the right tool for "forget this browser".
        if (!$this -> isCurrent) {
            $revoke = new Button();
            $revoke -> type = 'button';
            $revoke -> class = 'Btn ms-auto RevokeSessionButton';
            $revoke -> attributes['data-token-id'] = (string) $this -> tokenId;
            $revoke -> addContent('Revoke');
            $this -> addContent($revoke);
        }

        return parent::toDOM();
    }

    /**
     * A dumb, no-dependency browser/OS guess from the raw user-agent string -
     * good enough to tell devices apart in a list, not meant to be exact.
     * Deliberately not a real UA-parsing library: this is display-only,
     * never a security decision.
     */
    private static function describe(?string $user_agent): string
    {
        if ($user_agent === null || $user_agent === '') {
            return 'Unknown device';
        }

        $os = match (true) {
            str_contains($user_agent, 'iPhone'), str_contains($user_agent, 'iPad') => 'iOS',
            str_contains($user_agent, 'Android') => 'Android',
            str_contains($user_agent, 'Windows') => 'Windows',
            str_contains($user_agent, 'Mac OS X') => 'macOS',
            str_contains($user_agent, 'Linux') => 'Linux',
            default => null,
        };

        $browser = match (true) {
            str_contains($user_agent, 'Edg/') => 'Edge',
            str_contains($user_agent, 'OPR/'), str_contains($user_agent, 'Opera') => 'Opera',
            str_contains($user_agent, 'Firefox/') => 'Firefox',
            str_contains($user_agent, 'CriOS/'), str_contains($user_agent, 'Chrome/') => 'Chrome',
            str_contains($user_agent, 'Safari/') => 'Safari',
            default => null,
        };

        if ($browser !== null && $os !== null) {
            return $browser . ' on ' . $os;
        }

        return $browser ?? $os ?? 'Unknown device';
    }
}
