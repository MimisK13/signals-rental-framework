<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesIsolatedSqliteMigrations;

uses(UsesIsolatedSqliteMigrations::class);

/**
 * P12 tax zone migrations in reverse rollback order (last migrated first).
 *
 * @return list<string>
 */
function taxZoneMigrationRollbackPaths(): array
{
    return [
        'app/Domain/Tax/Database/Migrations/2026_07_03_134742_add_tax_category_foreign_key_to_tax_rules_table.php',
        'app/Domain/Tax/Database/Migrations/2026_07_03_134741_add_tax_zone_foreign_keys_to_tax_rules_table.php',
        'app/Domain/Tax/Database/Migrations/2026_07_03_134740_add_tax_category_foreign_keys_to_products_table.php',
        'app/Domain/Tax/Database/Migrations/2026_07_03_134740_add_tax_zone_foreign_key_to_stores_table.php',
        'app/Domain/Tax/Database/Migrations/2026_07_03_134739_add_tax_zone_foreign_key_to_members_table.php',
        'app/Domain/Tax/Database/Migrations/2026_07_03_134739_create_tax_zone_members_table.php',
        'app/Domain/Tax/Database/Migrations/2026_07_03_134739_create_tax_categories_table.php',
        'app/Domain/Tax/Database/Migrations/2026_07_03_134738_create_tax_zones_table.php',
    ];
}

describe('tax zone migrations (sqlite)', function () {
    it('creates tax zone tables and extends tax_rules', function () {
        expect(Schema::hasTable('tax_zones'))->toBeTrue()
            ->and(Schema::hasTable('tax_zone_members'))->toBeTrue()
            ->and(Schema::hasTable('tax_categories'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'tax_zone_id'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'buyer_type'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'tax_exempt'))->toBeTrue()
            ->and(Schema::hasColumn('stores', 'tax_zone_id'))->toBeTrue()
            ->and(Schema::hasColumn('tax_rules', 'seller_zone_id'))->toBeTrue()
            ->and(Schema::hasColumn('tax_rules', 'buyer_zone_id'))->toBeTrue()
            ->and(Schema::hasColumn('tax_rules', 'buyer_type'))->toBeTrue()
            ->and(Schema::hasColumn('tax_rules', 'has_tax_id'))->toBeTrue()
            ->and(Schema::hasColumn('tax_rules', 'result_type'))->toBeTrue()
            ->and(Schema::hasColumn('tax_rules', 'legal_text'))->toBeTrue();
    });

    it('rolls back and re-applies tax zone migrations cleanly', function () {
        foreach (taxZoneMigrationRollbackPaths() as $path) {
            Artisan::call('migrate:rollback', [
                '--path' => $path,
                '--force' => true,
            ]);
        }

        expect(Schema::hasTable('tax_zones'))->toBeFalse()
            ->and(Schema::hasTable('tax_zone_members'))->toBeFalse()
            ->and(Schema::hasTable('tax_categories'))->toBeFalse();

        foreach (array_reverse(taxZoneMigrationRollbackPaths()) as $path) {
            Artisan::call('migrate', [
                '--path' => $path,
                '--force' => true,
            ]);
        }

        expect(Schema::hasTable('tax_zones'))->toBeTrue()
            ->and(Schema::hasColumn('tax_rules', 'result_type'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'buyer_type'))->toBeTrue();
    });
});
