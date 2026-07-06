<?php

use App\Actions\Documents\SetDefaultDocumentTemplate;
use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\UsesPostgres;

/*
| PostgreSQL lane: partial unique index uq_document_templates_default_per_type
| rejects two concurrent defaults for the same store + document type.
|
|   php artisan test --compact --group=pgsql tests/Pgsql/DocumentTemplateDefaultUniquePostgresTest.php
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('rejects a second default template for the same store and document type', function () {
    $store = Store::factory()->create();

    DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'slug' => 'default-quote-a',
        'is_default' => true,
    ]);

    expect(fn () => DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'slug' => 'default-quote-b',
        'is_default' => true,
    ]))->toThrow(QueryException::class);
});

it('allows multiple non-default templates for the same store and document type', function () {
    $store = Store::factory()->create();

    DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'slug' => 'alt-quote-a',
        'is_default' => false,
    ]);

    DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'slug' => 'alt-quote-b',
        'is_default' => false,
    ]);

    expect(DocumentTemplate::query()
        ->where('store_id', $store->id)
        ->where('document_type', 'quote')
        ->count())->toBe(2);
});

it('translates a raced default-template unique violation into a validation error', function () {
    $store = Store::factory()->create();

    $first = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'slug' => 'quote-a',
        'is_default' => false,
    ]);

    $second = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'slug' => 'quote-b',
        'is_default' => false,
    ]);

    $injected = false;
    DB::listen(function ($query) use (&$injected, $second): void {
        if ($injected) {
            return;
        }

        if (! str_contains($query->sql, 'document_templates')
            || ! str_contains($query->sql, 'is_default')
            || ! preg_match('/update/i', $query->sql)) {
            return;
        }

        $injected = true;

        DB::connection($query->connectionName)
            ->table('document_templates')
            ->where('id', $second->id)
            ->update(['is_default' => true]);
    });

    expect(fn () => (new SetDefaultDocumentTemplate)($first))
        ->toThrow(ValidationException::class);
});
