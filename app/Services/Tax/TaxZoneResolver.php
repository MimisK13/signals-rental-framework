<?php

namespace App\Services\Tax;

use App\Models\TaxZone;
use App\Models\TaxZoneMember;

/**
 * Resolves a country ISO code to the highest-priority active tax zone.
 */
class TaxZoneResolver
{
    public function resolveCountryCode(string $countryCode): ?TaxZone
    {
        $countryCode = strtoupper(trim($countryCode));

        if ($countryCode === '') {
            return null;
        }

        return TaxZone::query()
            ->where('is_active', true)
            ->whereHas('members', fn ($query) => $query->where('country_code', $countryCode))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();
    }

    public function resolveForMember(?int $taxZoneId, ?string $countryCode): ?TaxZone
    {
        if ($taxZoneId !== null) {
            return TaxZone::query()
                ->whereKey($taxZoneId)
                ->where('is_active', true)
                ->first();
        }

        if ($countryCode === null || $countryCode === '') {
            return null;
        }

        return $this->resolveCountryCode($countryCode);
    }

    public function resolveForStore(?int $taxZoneId, ?string $countryCode): ?TaxZone
    {
        return $this->resolveForMember($taxZoneId, $countryCode);
    }

    /**
     * @return list<string>
     */
    public function countryCodesForZone(TaxZone $taxZone): array
    {
        return TaxZoneMember::query()
            ->where('tax_zone_id', $taxZone->id)
            ->orderBy('country_code')
            ->pluck('country_code')
            ->all();
    }
}
