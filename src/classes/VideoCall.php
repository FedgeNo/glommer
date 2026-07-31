<?php

declare(strict_types=1);

/**
 * One-to-one video calling between two people reading the same message thread.
 *
 * The media never touches this server. STUN is the only outside help involved,
 * and all it does is tell a browser what its own public address looks like from
 * the far side of its router - one small request, no video. The relay that
 * WOULD carry media (TURN) is deliberately absent, so a pair with no direct path
 * between them is simply never offered a call rather than quietly having their
 * video proxied.
 *
 * Whether a direct path exists can't be predicted from either end, so the client
 * proves it instead: it opens a data-channel-only connection first, which needs
 * no camera and shows nothing, and only offers the call if that reaches
 * 'connected'. This class holds what both halves of that need to agree on.
 */
class VideoCall
{
    /**
     * The STUN endpoints handed to the browser. Two of them so a single one
     * being unreachable doesn't cost the call - they answer the same question
     * and the first to reply wins.
     */
    private const STUN_SERVERS = [
        'stun:stun.l.google.com:19302',
        'stun:stun1.l.google.com:19302',
    ];

    /**
     * What one side may send the other. The payloads themselves are opaque here
     * and never parsed - only the kind is checked, so a signal can't name
     * something the client has no handler for. There is no candidate kind: the
     * client gathers before it sends, so every candidate rides inside the
     * description and one message each way sets up a call.
     */
    private const SIGNAL_TYPES = ['probeOffer', 'probeAnswer', 'offer', 'answer', 'decline', 'hangup'];

    /**
     * A session description is a few kilobytes; this is far above anything
     * legitimate and stops the relay being used to push bulk data at someone.
     */
    public const MAX_SIGNAL_BYTES = 16384;

    /**
     * RTCConfiguration.iceServers, as the browser wants it. No TURN entry, ever
     * - see the class docblock.
     *
     * @return array<int, array{urls: string}>
     */
    public static function iceServers(): array
    {
        return array_map(static fn (string $url): array => ['urls' => $url], self::STUN_SERVERS);
    }

    public static function isSignalType(string $type): bool
    {
        return in_array($type, self::SIGNAL_TYPES, true);
    }

    /**
     * Which of the two opens the probe. Both sides run the same code, so
     * without a rule they would both offer at once and neither answer; the
     * lower id going first is arbitrary but agreed.
     */
    public static function initiates(int $user_id, int $other_user_id): bool
    {
        return $user_id < $other_user_id;
    }
}
