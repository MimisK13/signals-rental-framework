<?php

namespace App\Services\Tax;

use App\Contracts\Tax\VatNumberValidator;
use App\Exceptions\Tax\ViesServiceUnavailableException;
use App\ValueObjects\Tax\VatValidationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DefaultVatNumberValidator implements VatNumberValidator
{
    /** @var list<string> */
    private const VIES_COUNTRY_CODES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'HR', 'HU',
        'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI',
    ];

    public function validate(string $taxNumber): VatValidationResult
    {
        $parsed = $this->parseTaxNumber($taxNumber);

        if ($parsed === null) {
            return new VatValidationResult(
                isValid: false,
                source: 'format',
            );
        }

        if (in_array($parsed['country_code'], ['GB', 'UK'], true)) {
            return $this->validateUkNumber($parsed['country_code'], $parsed['vat_number']);
        }

        if (in_array($parsed['country_code'], self::VIES_COUNTRY_CODES, true)) {
            return $this->validateViaVies($parsed['country_code'], $parsed['vat_number']);
        }

        return new VatValidationResult(
            isValid: false,
            source: 'unsupported_country',
            countryCode: $parsed['country_code'],
            vatNumber: $parsed['vat_number'],
        );
    }

    /**
     * @return array{country_code: string, vat_number: string}|null
     */
    private function parseTaxNumber(string $taxNumber): ?array
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($taxNumber)) ?? '');

        if ($normalized === '' || strlen($normalized) < 4) {
            return null;
        }

        $countryCode = substr($normalized, 0, 2);
        $vatNumber = substr($normalized, 2);

        if ($vatNumber === '') {
            return null;
        }

        if ($countryCode === 'UK') {
            $countryCode = 'GB';
        }

        if ($countryCode === 'GR') {
            $countryCode = 'EL';
        }

        return [
            'country_code' => $countryCode,
            'vat_number' => $vatNumber,
        ];
    }

    private function validateUkNumber(string $countryCode, string $vatNumber): VatValidationResult
    {
        $isValid = $this->passesUkChecksum($vatNumber);

        return new VatValidationResult(
            isValid: $isValid,
            source: 'uk_checksum',
            countryCode: $countryCode,
            vatNumber: $vatNumber,
        );
    }

    private function passesUkChecksum(string $vatNumber): bool
    {
        if (preg_match('/^\d{9}$/', $vatNumber) !== 1) {
            return false;
        }

        $weights = [8, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($index = 0; $index < 7; $index++) {
            $sum += ((int) $vatNumber[$index]) * $weights[$index];
        }

        $sum += ((int) $vatNumber[7]) * 9;
        $sum += ((int) $vatNumber[8]) * 10;

        return $sum % 97 === 0;
    }

    private function validateViaVies(string $countryCode, string $vatNumber): VatValidationResult
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->post(config('tax.vies_rest_url'), [
                    'countryCode' => $countryCode,
                    'vatNumber' => $vatNumber,
                ]);
        } catch (ConnectionException $exception) {
            throw new ViesServiceUnavailableException(
                'VIES registry is unavailable: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new ViesServiceUnavailableException(
                'VIES registry returned HTTP '.$response->status(),
            );
        }

        if ($response->failed()) {
            return new VatValidationResult(
                isValid: false,
                source: 'vies',
                countryCode: $countryCode,
                vatNumber: $vatNumber,
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $isValid = (bool) ($body['valid'] ?? $body['isValid'] ?? false);

        return new VatValidationResult(
            isValid: $isValid,
            source: 'vies',
            traderName: isset($body['name']) ? (string) $body['name'] : null,
            traderAddress: isset($body['address']) ? (string) $body['address'] : null,
            countryCode: $countryCode,
            vatNumber: $vatNumber,
        );
    }
}
