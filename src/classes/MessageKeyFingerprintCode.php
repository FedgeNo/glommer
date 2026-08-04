<?php

declare(strict_types=1);

/**
 * Where the safety code appears. Empty until the browser works it out from the
 * keys it is actually using - see MessageKeyFingerprint.
 */
class MessageKeyFingerprintCode extends Div
{
    public ?string $class = 'MessageKeyFingerprintCode';
}
