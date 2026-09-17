<?php

declare(strict_types=1);

/** A remote failure, without the submitted text or response body. */
final class GoogleTranslationException extends \RuntimeException {
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly ?int $retryAfterSeconds = null,
        public readonly int $transportCode = 0,
        public readonly ?int $rpcCode = null
    ) {
        parent::__construct($message, $httpStatus);
    }
}
