<?php

declare(strict_types=1);

/** The columns FediverseDelivery's queries read off a FediverseDeliveries row. */
class FediverseDeliveryData
{
    public ?int $deliveryId = null;
    public ?int $actorUserId = null;
    public ?string $inboxURL = null;
    public ?string $activity = null;
    public ?int $attempts = null;
    public ?string $nextAttemptAt = null;
    public ?string $lastError = null;
    public ?string $createdAt = null;
}
