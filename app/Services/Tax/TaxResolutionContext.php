<?php

namespace App\Services\Tax;

use App\Enums\TaxBuyerType;
use App\Enums\TaxUnresolvedZoneFallback;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;

/**
 * Seller/buyer zone context for zone-aware tax resolution during document tax.
 */
class TaxResolutionContext
{
    public function __construct(
        public readonly ?int $sellerZoneId,
        public readonly ?int $buyerZoneId,
        public readonly TaxBuyerType $buyerType,
        public readonly bool $hasTaxId,
        public readonly bool $taxExempt,
    ) {}

    public static function fromOpportunity(Opportunity $opportunity): self
    {
        $opportunity->loadMissing([
            'member.taxZone',
            'member.addresses.country',
            'store.taxZone',
            'store.country',
        ]);

        $resolver = app(TaxZoneResolver::class);
        $fallback = TaxUnresolvedZoneFallback::tryFrom(
            (string) settings('tax.unresolved_zone_fallback', TaxUnresolvedZoneFallback::SellerDomestic->value),
        ) ?? TaxUnresolvedZoneFallback::SellerDomestic;

        $sellerZoneId = self::resolveSellerZoneId($opportunity->store, $resolver);
        $buyerZoneId = self::resolveBuyerZoneId($opportunity->member, $resolver, $sellerZoneId, $fallback);

        return new self(
            sellerZoneId: $sellerZoneId,
            buyerZoneId: $buyerZoneId,
            buyerType: $opportunity->member?->buyer_type ?? TaxBuyerType::B2b,
            hasTaxId: self::memberHasTaxId($opportunity->member),
            taxExempt: (bool) ($opportunity->member?->tax_exempt ?? false),
        );
    }

    public function canMatchZoneRules(): bool
    {
        return $this->sellerZoneId !== null && $this->buyerZoneId !== null;
    }

    private static function resolveSellerZoneId(?Store $store, TaxZoneResolver $resolver): ?int
    {
        if ($store === null) {
            return null;
        }

        return $resolver->resolveForStore(
            $store->tax_zone_id,
            self::storeCountryCode($store),
        )?->id;
    }

    private static function resolveBuyerZoneId(
        ?Member $member,
        TaxZoneResolver $resolver,
        ?int $sellerZoneId,
        TaxUnresolvedZoneFallback $fallback,
    ): ?int {
        if ($member === null) {
            return self::fallbackBuyerZoneId($sellerZoneId, $fallback);
        }

        $zone = $resolver->resolveForMember(
            $member->tax_zone_id,
            self::memberCountryCode($member),
        );

        if ($zone !== null) {
            return $zone->id;
        }

        return self::fallbackBuyerZoneId($sellerZoneId, $fallback);
    }

    private static function fallbackBuyerZoneId(?int $sellerZoneId, TaxUnresolvedZoneFallback $fallback): ?int
    {
        if ($fallback === TaxUnresolvedZoneFallback::SellerDomestic && $sellerZoneId !== null) {
            return $sellerZoneId;
        }

        return null;
    }

    private static function memberHasTaxId(?Member $member): bool
    {
        if ($member === null) {
            return false;
        }

        $taxNumber = trim((string) ($member->tax_number ?? ''));

        if ($taxNumber === '') {
            return false;
        }

        return $member->tax_number_validated === true;
    }

    private static function memberCountryCode(Member $member): ?string
    {
        $primary = $member->addresses->firstWhere('is_primary', true)
            ?? $member->addresses->first();

        $code = $primary?->country?->code;

        return is_string($code) && $code !== '' ? strtoupper($code) : null;
    }

    private static function storeCountryCode(Store $store): ?string
    {
        if (is_string($store->country_code) && $store->country_code !== '') {
            return strtoupper($store->country_code);
        }

        $code = $store->country?->code;

        return is_string($code) && $code !== '' ? strtoupper($code) : null;
    }
}
