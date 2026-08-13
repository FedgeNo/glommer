<?php

declare(strict_types=1);

/**
 * Sends an ActivityPub document, and handles the two things every one of these
 * endpoints has to get right.
 *
 * A remote server dereferencing an actor or a collection carries no cookie, so
 * the session init.php opened is discarded rather than left behind as a file -
 * on a federating instance that is otherwise thousands of orphaned sessions a
 * day. Only when no cookie was presented, so a signed-in person opening the
 * same URL in a browser keeps theirs.
 *
 * The body is the document itself, not an envelope: this is a published
 * document with a stable URI, and the network reads it as-is. That is the whole
 * difference from JSONResponse, which wraps api/ replies for our own client.
 */
class ActivityPubResponse
{
    public const CONTENT_TYPE = 'application/activity+json';

    /**
     * WebFinger's answer is a JRD and says so - a different document under a
     * different specification (RFC 7033 §10.2), which happens to be reached
     * through the same code as everything else here.
     */
    public const JRD_CONTENT_TYPE = 'application/jrd+json';

    /** Drops the session a cookieless server-to-server request opened. */
    public static function discardAnonymousSession(): void
    {
        if (!isset($_COOKIE[session_name()])) {
            session_destroy();
        }
    }

    /**
     * The type is sent from here rather than by the caller: a header set
     * before this runs is replaced by this one, which is how WebFinger came to
     * announce itself as ActivityPub for as long as it did.
     *
     * @param array<string, mixed> $document
     */
    public static function send(array $document, string $content_type = self::CONTENT_TYPE): never
    {
        self::discardAnonymousSession();

        header('Content-Type: ' . $content_type);

        // Slashes unescaped because every id in here is a URL and readable ids
        // matter when someone is debugging federation by eye.
        echo json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        exit;
    }

    public static function notFound(): never
    {
        self::discardAnonymousSession();

        http_response_code(404);

        exit;
    }

    /**
     * The local member behind one of these URLs, or null when there isn't one
     * that can federate. A shadow row for a remote account is deliberately not
     * one: we don't publish an actor document for somebody else's account, and
     * answering for one would be claiming to speak for them.
     */
    public static function localUser(string $username): ?User
    {
        $user = User::byUsername($username);

        if ($user === null || $user -> remoteActorURI !== null || (int) $user -> banned === 1) {
            return null;
        }

        return $user;
    }

    /**
     * An OrderedCollection whose items are inlined. Used for the small
     * collections - a follower list is read whole by the servers that ask for
     * it, and paging one that fits in a single response buys nothing.
     *
     * @param string[] $items
     * @return array<string, mixed>
     */
    public static function orderedCollection(string $id, array $items): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $id,
            'type' => 'OrderedCollection',
            'totalItems' => count($items),
            'orderedItems' => array_values($items),
        ];
    }
}
