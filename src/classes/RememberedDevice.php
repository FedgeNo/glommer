<?php

declare(strict_types=1);

/**
 * One row on the Settings "Remembered devices" list: a loosely-parsed
 * browser/OS label from the token's stored user-agent, its IP, when it was
 * first seen and last used, and a Revoke button - everything RememberToken
 * tracks per token except the validator itself, which never leaves the
 * server. Fetched by RememberedDeviceList;
 * the selector is carried only to compare against the current browser's
 * cookie, never shown to the user.
 */
class RememberedDevice extends Div
{
    public ?string $class = 'RememberedDevice';
    public array $mixins = ['d-flex', 'align-items-center', 'gap-3'];

    public ?int $tokenId = null;
    public ?string $selector = null;
    public ?string $userAgent = null;
    public ?string $ipAddress = null;
    public ?string $createdAt = null;
    public ?string $lastUsedAt = null;

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $is_current = $this -> selector === RememberToken::currentSelector();

        $this -> attributes['data-token-id'] = (string) $this -> tokenId;

        $info = new Div();
        $info -> mixins = ['d-flex', 'flex-column', 'gap-1'];

        $label = self::describe($this -> userAgent, $words);

        if ($is_current) {
            $label .= (string) ($words['thisDevice'] ?? '');
        }

        $info -> addContent(new Paragraph($label));

        $detail_line = new Paragraph($this -> ipAddress !== null ? $this -> ipAddress . ' - ' : null);
        $detail_line -> mixins = ['muted'];
        $detail_line -> addContent((string) ($words['lastUsed']['before'] ?? ''));
        $detail_line -> addContent(new RelativeTime($this -> lastUsedAt));
        $detail_line -> addContent((string) ($words['lastUsed']['after'] ?? ''));
        $info -> addContent($detail_line);

        $this -> addContent($info);

        // The current device isn't revocable from its own row - revoking it
        // would log this very session's persistent cookie out from under the
        // user mid-visit; logout is the right tool for "forget this browser".
        if (!$is_current) {
            $this -> addContent(new RememberedDeviceRevokeButton((int) $this -> tokenId));
        }

        return parent::toDOM();
    }

    /**
     * A dumb, no-dependency browser/OS guess from the raw user-agent string -
     * good enough to tell devices apart in a list, not meant to be exact.
     * Deliberately not a real UA-parsing library: this is display-only,
     * never a security decision.
     *
     * The browser and OS names themselves are brand names, not prose - the
     * same word in every locale - so only the phrase joining them comes from
     * $words.
     *
     * @param array<string, mixed> $words
     */
    private static function describe(?string $user_agent, array $words): string
    {
        if ($user_agent === null || $user_agent === '') {
            return (string) ($words['unknownDevice'] ?? '');
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
            return str_replace(['{browser}', '{os}'], [$browser, $os], (string) ($words['browserOnOS'] ?? ''));
        }

        return $browser ?? $os ?? (string) ($words['unknownDevice'] ?? '');
    }
}
