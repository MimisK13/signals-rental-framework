<?php

namespace Database\Seeders;

use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Models\TaxRule;
use App\Models\TaxZone;
use Illuminate\Database\Seeder;

/**
 * Seeds common UK-seller zone-matching rules for cross-border scenarios.
 *
 * Priority ladder (higher wins; null buyer_type / has_tax_id are wildcards):
 *  110 — Tax exempt supply (government/charity, UK→UK)
 *  100 — UK domestic (UK→UK, any buyer)
 *   90 — UK to EU business reverse charge (business + validated tax ID)
 *   80 — UK to EU business without tax ID
 *   70 — UK to EU individual
 *   65 — Outside scope supply (business + validated tax ID, UK→World)
 *   60 — UK export zero rated (UK→World, any buyer)
 */
class TaxZoneRuleSeeder extends Seeder
{
    public function run(): void
    {
        $uk = TaxZone::query()->where('slug', 'uk')->first();
        $euMember = TaxZone::query()->where('slug', 'eu_member')->first();
        $world = TaxZone::query()->where('slug', 'world')->first();

        if ($uk === null || $euMember === null || $world === null) {
            return;
        }

        $rules = [
            [
                'name' => 'UK domestic',
                'seller_zone_id' => $uk->id,
                'buyer_zone_id' => $uk->id,
                'buyer_type' => null,
                'has_tax_id' => null,
                'result_type' => TaxResultType::Standard,
                'priority' => 100,
            ],
            [
                'name' => 'UK to EU B2B reverse charge',
                'seller_zone_id' => $uk->id,
                'buyer_zone_id' => $euMember->id,
                'buyer_type' => TaxBuyerType::Business,
                'has_tax_id' => true,
                'result_type' => TaxResultType::ReverseCharge,
                'legal_text' => 'Reverse charge: customer to account for VAT.',
                'priority' => 90,
            ],
            [
                'name' => 'UK to EU B2B without tax ID',
                'seller_zone_id' => $uk->id,
                'buyer_zone_id' => $euMember->id,
                'buyer_type' => TaxBuyerType::Business,
                'has_tax_id' => false,
                'result_type' => TaxResultType::Standard,
                'priority' => 80,
            ],
            [
                'name' => 'UK to EU B2C',
                'seller_zone_id' => $uk->id,
                'buyer_zone_id' => $euMember->id,
                'buyer_type' => TaxBuyerType::Individual,
                'has_tax_id' => null,
                'result_type' => TaxResultType::Standard,
                'priority' => 70,
            ],
            [
                'name' => 'UK export zero rated',
                'seller_zone_id' => $uk->id,
                'buyer_zone_id' => $world->id,
                'buyer_type' => null,
                'has_tax_id' => null,
                'result_type' => TaxResultType::ZeroRated,
                'legal_text' => 'Zero rated export supply.',
                'priority' => 60,
            ],
            [
                'name' => 'Tax exempt supply (government)',
                'seller_zone_id' => $uk->id,
                'buyer_zone_id' => $uk->id,
                'buyer_type' => TaxBuyerType::Government,
                'has_tax_id' => null,
                'result_type' => TaxResultType::Exempt,
                'legal_text' => 'Exempt supply.',
                'priority' => 110,
            ],
            [
                'name' => 'Tax exempt supply (charity)',
                'seller_zone_id' => $uk->id,
                'buyer_zone_id' => $uk->id,
                'buyer_type' => TaxBuyerType::Charity,
                'has_tax_id' => null,
                'result_type' => TaxResultType::Exempt,
                'legal_text' => 'Exempt supply.',
                'priority' => 110,
            ],
            [
                'name' => 'Outside scope supply',
                'seller_zone_id' => $uk->id,
                'buyer_zone_id' => $world->id,
                'buyer_type' => TaxBuyerType::Business,
                'has_tax_id' => true,
                'result_type' => TaxResultType::OutsideScope,
                'legal_text' => 'Outside the scope of UK VAT.',
                'priority' => 65,
            ],
        ];

        foreach ($rules as $rule) {
            TaxRule::query()->updateOrCreate(
                [
                    'name' => $rule['name'],
                    'seller_zone_id' => $rule['seller_zone_id'],
                    'buyer_zone_id' => $rule['buyer_zone_id'],
                    'buyer_type' => $rule['buyer_type'],
                    'has_tax_id' => $rule['has_tax_id'],
                ],
                [
                    'organisation_tax_class_id' => null,
                    'product_tax_class_id' => null,
                    'tax_rate_id' => null,
                    'result_type' => $rule['result_type'],
                    'legal_text' => $rule['legal_text'] ?? null,
                    'priority' => $rule['priority'],
                    'is_active' => true,
                ],
            );
        }

        TaxRule::query()
            ->where('name', 'Tax exempt supply')
            ->where('seller_zone_id', $uk->id)
            ->where('buyer_zone_id', $uk->id)
            ->whereNull('buyer_type')
            ->delete();
    }
}
