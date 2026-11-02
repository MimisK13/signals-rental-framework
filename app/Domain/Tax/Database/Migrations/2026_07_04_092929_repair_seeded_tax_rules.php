<?php

declare(strict_types=1);

use App\Domain\Tax\Enums\TaxBuyerType;
use App\Domain\Tax\Enums\TaxResultType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair shipped tax-rule buyer types and seeded zone-rule rows on existing installs.
 *
 * Admin-modified seeded rules are left untouched when their fields diverge from the
 * original seed snapshot matched below.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_rules')) {
            return;
        }

        $this->repairSeededZoneRules();

        DB::table('tax_rules')
            ->where('buyer_type', 'b2b')
            ->update(['buyer_type' => TaxBuyerType::Business->value]);

        DB::table('tax_rules')
            ->where('buyer_type', 'b2c')
            ->update(['buyer_type' => TaxBuyerType::Individual->value]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tax_rules')) {
            return;
        }

        DB::table('tax_rules')
            ->where('buyer_type', TaxBuyerType::Business->value)
            ->update(['buyer_type' => 'b2b']);

        DB::table('tax_rules')
            ->where('buyer_type', TaxBuyerType::Individual->value)
            ->update(['buyer_type' => 'b2c']);
    }

    private function repairSeededZoneRules(): void
    {
        $this->updateSeededRuleIfUnmodified(
            name: 'UK to EU B2B reverse charge',
            expected: [
                'buyer_type' => 'b2b',
                'has_tax_id' => true,
                'result_type' => TaxResultType::ReverseCharge->value,
                'priority' => 90,
                'legal_text' => 'Reverse charge: customer to account for VAT.',
            ],
            updates: [
                'buyer_type' => TaxBuyerType::Business->value,
            ],
        );

        $this->updateSeededRuleIfUnmodified(
            name: 'UK to EU B2B without tax ID',
            expected: [
                'buyer_type' => 'b2b',
                'has_tax_id' => false,
                'result_type' => TaxResultType::Standard->value,
                'priority' => 80,
                'legal_text' => null,
            ],
            updates: [
                'buyer_type' => TaxBuyerType::Business->value,
            ],
        );

        $this->updateSeededRuleIfUnmodified(
            name: 'UK to EU B2C',
            expected: [
                'buyer_type' => 'b2c',
                'has_tax_id' => null,
                'result_type' => TaxResultType::Standard->value,
                'priority' => 70,
                'legal_text' => null,
            ],
            updates: [
                'buyer_type' => TaxBuyerType::Individual->value,
            ],
        );

        $this->updateSeededRuleIfUnmodified(
            name: 'Outside scope supply',
            expected: [
                'buyer_type' => 'b2b',
                'has_tax_id' => true,
                'result_type' => TaxResultType::OutsideScope->value,
                'priority' => 40,
                'legal_text' => 'Outside the scope of UK VAT.',
            ],
            updates: [
                'buyer_type' => TaxBuyerType::Business->value,
                'priority' => 65,
            ],
        );

        $legacyExemptRule = DB::table('tax_rules')
            ->where('name', 'Tax exempt supply')
            ->whereNotNull('seller_zone_id')
            ->whereColumn('seller_zone_id', 'buyer_zone_id')
            ->whereNull('buyer_type')
            ->whereNull('has_tax_id')
            ->where('result_type', TaxResultType::Exempt->value)
            ->where('priority', 50)
            ->where('legal_text', 'Exempt supply.')
            ->first();

        if ($legacyExemptRule !== null) {
            DB::table('tax_rules')->where('id', $legacyExemptRule->id)->delete();

            $this->insertExemptRuleIfMissing(
                name: 'Tax exempt supply (government)',
                sellerZoneId: (int) $legacyExemptRule->seller_zone_id,
                buyerZoneId: (int) $legacyExemptRule->buyer_zone_id,
                buyerType: TaxBuyerType::Government->value,
            );

            $this->insertExemptRuleIfMissing(
                name: 'Tax exempt supply (charity)',
                sellerZoneId: (int) $legacyExemptRule->seller_zone_id,
                buyerZoneId: (int) $legacyExemptRule->buyer_zone_id,
                buyerType: TaxBuyerType::Charity->value,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $updates
     */
    private function updateSeededRuleIfUnmodified(
        string $name,
        array $expected,
        array $updates,
    ): void {
        $query = DB::table('tax_rules')
            ->where('name', $name);

        foreach ($expected as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        $ruleId = $query->value('id');

        if ($ruleId === null) {
            return;
        }

        DB::table('tax_rules')
            ->where('id', $ruleId)
            ->update(array_merge($updates, ['updated_at' => now()]));
    }

    private function insertExemptRuleIfMissing(
        string $name,
        int $sellerZoneId,
        int $buyerZoneId,
        string $buyerType,
    ): void {
        $exists = DB::table('tax_rules')
            ->where('name', $name)
            ->where('seller_zone_id', $sellerZoneId)
            ->where('buyer_zone_id', $buyerZoneId)
            ->where('buyer_type', $buyerType)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('tax_rules')->insert([
            'name' => $name,
            'seller_zone_id' => $sellerZoneId,
            'buyer_zone_id' => $buyerZoneId,
            'buyer_type' => $buyerType,
            'has_tax_id' => null,
            'organisation_tax_class_id' => null,
            'product_tax_class_id' => null,
            'tax_rate_id' => null,
            'tax_category_id' => null,
            'result_type' => TaxResultType::Exempt->value,
            'legal_text' => 'Exempt supply.',
            'priority' => 110,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
