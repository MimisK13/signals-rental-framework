<?php

use App\Enums\MembershipType;
use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Models\TaxRule;
use App\Models\TaxZone;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

const TAX_BUYER_TYPE_WIDEN_MIGRATION = 'database/migrations/2026_07_04_092927_widen_tax_buyer_type_columns.php';
const TAX_BUYER_TYPE_DATA_MIGRATION = 'database/migrations/2026_07_04_092928_migrate_tax_buyer_type_values_and_seeded_rules.php';

beforeEach(function () {
    TaxZone::factory()->uk()->create(['slug' => 'uk']);
    TaxZone::factory()->euMember()->create(['slug' => 'eu_member']);
    TaxZone::factory()->world()->create(['slug' => 'world']);
});

function rollbackTaxBuyerTypeMigrations(): void
{
    Artisan::call('migrate:rollback', [
        '--path' => TAX_BUYER_TYPE_DATA_MIGRATION,
        '--force' => true,
    ]);

    Artisan::call('migrate:rollback', [
        '--path' => TAX_BUYER_TYPE_WIDEN_MIGRATION,
        '--force' => true,
    ]);
}

function runTaxBuyerTypeMigrations(): void
{
    Artisan::call('migrate', [
        '--path' => TAX_BUYER_TYPE_WIDEN_MIGRATION,
        '--force' => true,
    ]);

    Artisan::call('migrate', [
        '--path' => TAX_BUYER_TYPE_DATA_MIGRATION,
        '--force' => true,
    ]);
}

it('migrates legacy b2b and b2c member buyer types to business and individual', function () {
    rollbackTaxBuyerTypeMigrations();

    $legacyBusinessId = DB::table('members')->insertGetId([
        'name' => 'Legacy Business',
        'membership_type' => MembershipType::Organisation->value,
        'buyer_type' => 'b2b',
        'tax_exempt' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $legacyIndividualId = DB::table('members')->insertGetId([
        'name' => 'Legacy Individual',
        'membership_type' => MembershipType::Contact->value,
        'buyer_type' => 'b2c',
        'tax_exempt' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runTaxBuyerTypeMigrations();

    expect(DB::table('members')->where('id', $legacyBusinessId)->value('buyer_type'))
        ->toBe(TaxBuyerType::Business->value)
        ->and(DB::table('members')->where('id', $legacyIndividualId)->value('buyer_type'))
        ->toBe(TaxBuyerType::Individual->value);
});

it('repairs the legacy exempt seed row and raises outside scope priority when unmodified', function () {
    rollbackTaxBuyerTypeMigrations();

    $uk = TaxZone::query()->where('slug', 'uk')->firstOrFail();
    $world = TaxZone::query()->where('slug', 'world')->firstOrFail();

    DB::table('tax_rules')->insert([
        'name' => 'Tax exempt supply',
        'seller_zone_id' => $uk->id,
        'buyer_zone_id' => $uk->id,
        'buyer_type' => null,
        'has_tax_id' => null,
        'organisation_tax_class_id' => null,
        'product_tax_class_id' => null,
        'tax_rate_id' => null,
        'tax_category_id' => null,
        'result_type' => TaxResultType::Exempt->value,
        'legal_text' => 'Exempt supply.',
        'priority' => 50,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('tax_rules')->insert([
        'name' => 'Outside scope supply',
        'seller_zone_id' => $uk->id,
        'buyer_zone_id' => $world->id,
        'buyer_type' => 'b2b',
        'has_tax_id' => true,
        'organisation_tax_class_id' => null,
        'product_tax_class_id' => null,
        'tax_rate_id' => null,
        'tax_category_id' => null,
        'result_type' => TaxResultType::OutsideScope->value,
        'legal_text' => 'Outside the scope of UK VAT.',
        'priority' => 40,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runTaxBuyerTypeMigrations();

    expect(DB::table('tax_rules')->where('name', 'Tax exempt supply')->exists())->toBeFalse()
        ->and(DB::table('tax_rules')->where('name', 'Tax exempt supply (government)')->value('buyer_type'))
        ->toBe(TaxBuyerType::Government->value)
        ->and(DB::table('tax_rules')->where('name', 'Tax exempt supply (charity)')->value('buyer_type'))
        ->toBe(TaxBuyerType::Charity->value)
        ->and(DB::table('tax_rules')->where('name', 'Outside scope supply')->value('priority'))->toBe(65)
        ->and(DB::table('tax_rules')->where('name', 'Outside scope supply')->value('buyer_type'))
        ->toBe(TaxBuyerType::Business->value);
});

it('leaves admin-modified seeded rules untouched when their fields diverge', function () {
    rollbackTaxBuyerTypeMigrations();

    $uk = TaxZone::query()->where('slug', 'uk')->firstOrFail();
    $world = TaxZone::query()->where('slug', 'world')->firstOrFail();

    DB::table('tax_rules')->insert([
        'name' => 'Outside scope supply',
        'seller_zone_id' => $uk->id,
        'buyer_zone_id' => $world->id,
        'buyer_type' => 'b2b',
        'has_tax_id' => true,
        'organisation_tax_class_id' => null,
        'product_tax_class_id' => null,
        'tax_rate_id' => null,
        'tax_category_id' => null,
        'result_type' => TaxResultType::OutsideScope->value,
        'legal_text' => 'Custom legal text.',
        'priority' => 99,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runTaxBuyerTypeMigrations();

    $rule = TaxRule::query()->where('name', 'Outside scope supply')->firstOrFail();

    expect($rule->priority)->toBe(99)
        ->and($rule->legal_text)->toBe('Custom legal text.');
});
