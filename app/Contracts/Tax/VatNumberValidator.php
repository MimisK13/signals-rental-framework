<?php

namespace App\Contracts\Tax;

use App\Exceptions\Tax\ViesServiceUnavailableException;
use App\ValueObjects\Tax\VatValidationResult;

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
