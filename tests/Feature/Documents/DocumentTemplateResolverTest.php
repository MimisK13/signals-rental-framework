<?php

use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Services\Documents\DocumentTemplateResolver;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->resolver = app(DocumentTemplateResolver::class);
});

it('returns the requested template when it belongs to the store', function () {
    $store = Store::factory()->create();
    $template = DocumentTemplate::factory()->create([
        'store_id' => $store->id,
        'document_type' => 'invoice_standard',
        'is_active' => true,
    ]);

    $resolved = $this->resolver->resolveForGeneration($store->id, $template->id, null);

    expect($resolved->is($template))->toBeTrue();
});

it('rejects templates from another store', function () {
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $template = DocumentTemplate::factory()->create(['store_id' => $otherStore->id]);

    expect(fn () => $this->resolver->resolveForGeneration($store->id, $template->id, null))
        ->toThrow(ValidationException::class, 'does not belong to the source store');
});

it('requires either a template id or document type', function () {
    $store = Store::factory()->create();

    expect(fn () => $this->resolver->resolveForGeneration($store->id, null, null))
        ->toThrow(ValidationException::class, 'Either document_template_id or document_type is required');
});

it('selects the default active template for a document type', function () {
    $store = Store::factory()->create();
    DocumentTemplate::factory()->create([
        'store_id' => $store->id,
        'document_type' => 'invoice_standard',
        'is_active' => true,
        'is_default' => false,
    ]);
    $default = DocumentTemplate::factory()->create([
        'store_id' => $store->id,
        'document_type' => 'invoice_standard',
        'is_active' => true,
        'is_default' => true,
    ]);

    $resolved = $this->resolver->resolveForGeneration($store->id, null, 'invoice_standard');

    expect($resolved->is($default))->toBeTrue();
});

it('throws when no active template exists for the requested document type', function () {
    $store = Store::factory()->create();

    expect(fn () => $this->resolver->resolveForGeneration($store->id, null, 'invoice_standard'))
        ->toThrow(ValidationException::class, 'No active template exists');
});
