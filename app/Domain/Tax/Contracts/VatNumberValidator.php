<?php

declare(strict_types=1);

namespace App\Domain\Tax\Contracts;

use App\Domain\Tax\Exceptions\ViesServiceUnavailableException;
use App\Domain\Tax\ValueObjects\VatValidationResult;

interface VatNumberValidator
{
    /**
     * Validate a VAT / tax identification number.
     *
     * EU numbers are checked against VIES; UK numbers use format + checksum rules.
     * Throws {@see ViesServiceUnavailableException} when the
     * remote registry cannot be reached (distinct from an invalid number).
     */
    public function validate(string $taxNumber): VatValidationResult;
}
