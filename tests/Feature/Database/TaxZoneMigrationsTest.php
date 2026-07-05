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
        'database/migrations/2026_07_03_134740_extend_tax_rules_for_zone_resolution_table.php',
        'database/migrations/2026_07_03_134740_add_tax_context_columns_to_members_and_stores_table.php',
        'database/migrations/2026_07_03_134739_create_tax_zone_members_table.php',
        'database/migrations/2026_07_03_134739_create_tax_categories_table.php',
        'database/migrations/2026_07_03_134738_create_tax_zones_table.php',
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
            ->and(Schema::hasTable('tax_categories'))->toBeFalse()
            ->and(Schema::hasColumn('members', 'tax_zone_id'))->toBeFalse()
            ->and(Schema::hasColumn('stores', 'tax_zone_id'))->toBeFalse()
            ->and(Schema::hasColumn('tax_rules', 'seller_zone_id'))->toBeFalse();

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
