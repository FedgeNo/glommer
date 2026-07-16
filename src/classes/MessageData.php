<?php

declare(strict_types=1);

/**
 * The JSON-only shape of a Messages row - fetched directly by
 * Message::rowsBetween() for callers that just need the data (the
 * message-history AJAX endpoint feeding the client-side Message class), with
 * none of Message's HTMLObject rendering machinery a server-rendered page needs.
 */
class MessageData
{
    public ?int $messageId = null;
    public ?int $senderId = null;
    public ?int $recipientId = null;
    public ?string $body = null;
    public ?string $createdAt = null;
    // Selected by rowsBetween()'s shared query (SELECT *) but unused here -
    // declared so it doesn't land as a deprecated dynamic property.
    public ?int $reportsDismissed = null;
}
