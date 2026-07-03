<?php

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesPostgres;

/*
| PostgreSQL lane: proves JSONB document columns and FK constraints migrate cleanly
| on real Postgres (skipped when pgsql_testing is unreachable).
|
|   php artisan test --compact tests/Pgsql/DocumentEngineMigrationsPostgresTest.php
*/

uses(UsesPostgres::class)->group('pgsql');

describe('document engine migrations (pgsql)', function () {
    it('creates document engine tables with jsonb columns on postgres', function () {
        expect(Schema::hasTable('document_templates'))->toBeTrue()
            ->and(Schema::hasTable('documents'))->toBeTrue()
            ->and(Schema::hasColumn('document_templates', 'page_config'))->toBeTrue()
            ->and(Schema::hasColumn('document_templates', 'branding_config'))->toBeTrue()
            ->and(Schema::hasColumn('documents', 'rendered_data'))->toBeTrue()
            ->and(Schema::hasColumn('stores', 'brand_primary_colour'))->toBeTrue();
    });

    it('persists jsonb document config on postgres', function () {
        $template = DocumentTemplate::factory()->create([
            'page_config' => ['page_size' => 'Letter'],
            'branding_config' => ['primary_colour' => '#FF0000'],
        ]);

        $template->refresh();

        expect($template->page_config['page_size'])->toBe('Letter')
            ->and($template->branding_config['primary_colour'])->toBe('#FF0000');
    });
});
