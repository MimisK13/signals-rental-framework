<?php

namespace App\ValueObjects\Tax;

/**
 * Outcome of a VAT / tax identification number validation attempt.
 */
class VatValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string $source,
        public readonly ?string $traderName = null,
        public readonly ?string $traderAddress = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $vatNumber = null,
    ) {}

    /**
     * @return array{trader_name: ?string, trader_address: ?string, source: string, country_code: ?string, vat_number: ?string}
     */
    public function toStorageArray(): array
    {
        return [
            'trader_name' => $this->traderName,
            'trader_address' => $this->traderAddress,
            'source' => $this->source,
            'country_code' => $this->countryCode,
            'vat_number' => $this->vatNumber,
        ];
    }
}
