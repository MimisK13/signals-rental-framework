<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Document engine migrations in reverse rollback order (last migrated first).
 *
 * @return list<string>
 */
function documentEngineMigrationRollbackPaths(): array
{
    return [
        'database/migrations/2026_07_03_101151_add_store_branding_columns_to_stores_table.php',
        'database/migrations/2026_07_03_101150_create_documents_table.php',
        'database/migrations/2026_07_03_101149_create_document_sequences_table.php',
        'database/migrations/2026_07_03_101148_create_document_template_versions_table.php',
        'database/migrations/2026_07_03_101147_create_document_templates_table.php',
    ];
}

describe('document engine migrations (sqlite)', function () {
    it('creates all document engine tables', function () {
        expect(Schema::hasTable('document_templates'))->toBeTrue()
            ->and(Schema::hasTable('document_template_versions'))->toBeTrue()
            ->and(Schema::hasTable('documents'))->toBeTrue()
            ->and(Schema::hasTable('document_sequences'))->toBeTrue()
            ->and(Schema::hasColumn('stores', 'brand_logo_path'))->toBeTrue()
            ->and(Schema::hasColumn('stores', 'brand_primary_colour'))->toBeTrue()
            ->and(Schema::hasColumn('stores', 'brand_secondary_colour'))->toBeTrue()
            ->and(Schema::hasColumn('stores', 'brand_font_family'))->toBeTrue();
    });

    it('rolls back and re-applies document engine migrations cleanly', function () {
        foreach (documentEngineMigrationRollbackPaths() as $path) {
            Artisan::call('migrate:rollback', [
                '--path' => $path,
                '--force' => true,
            ]);
        }

        expect(Schema::hasTable('document_templates'))->toBeFalse()
            ->and(Schema::hasTable('document_template_versions'))->toBeFalse()
            ->and(Schema::hasTable('documents'))->toBeFalse()
            ->and(Schema::hasTable('document_sequences'))->toBeFalse()
            ->and(Schema::hasColumn('stores', 'brand_logo_path'))->toBeFalse();

        foreach (array_reverse(documentEngineMigrationRollbackPaths()) as $path) {
            Artisan::call('migrate', [
                '--path' => $path,
                '--force' => true,
            ]);
        }

        expect(Schema::hasTable('documents'))->toBeTrue()
            ->and(Schema::hasColumn('stores', 'brand_font_family'))->toBeTrue();
    });
});

describe('documents config', function () {
    it('loads document engine defaults', function () {
        expect(config('documents.pdf_driver'))->toBe('dompdf')
            ->and(config('documents.defaults.page_size'))->toBe('A4')
            ->and(config('documents.public_token_length'))->toBeGreaterThan(0);
    });
});
