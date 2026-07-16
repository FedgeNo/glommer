<?php

declare(strict_types=1);

/**
 * The columns TwoFactor::verifyCode() reads off a TwoFactorCodes row.
 */
class TwoFactorCodeData
{
    public ?int $codeId = null;
    public ?string $codeHash = null;
    public int $attempts = 0;
}
