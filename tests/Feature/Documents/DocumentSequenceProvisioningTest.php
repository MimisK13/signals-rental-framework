<?php

use App\Actions\Documents\GenerateDocument;
use App\Actions\Stores\CreateStore;
use App\Data\Documents\GenerateDocumentData;
use App\Data\Stores\CreateStoreData;
use App\Enums\DocumentOutputFormat;
use App\Enums\InvoiceType;
use App\Models\DocumentSequence;
use App\Models\Store;
use App\Models\User;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Documents\DocumentSequenceDefaults;
use App\Services\Documents\SystemDocumentTemplates;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('seeds document sequences when system templates are seeded for a store', function () {
    $store = Store::factory()->create();

    app(DocumentTemplateSeeder::class)->seedForStore($store);

    foreach (DocumentSequenceDefaults::seedableDocumentTypes() as $documentType) {
        expect(DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', $documentType)
            ->exists())->toBeTrue("Missing sequence for [{$documentType}]");
    }
});

it('seeds document sequences when a store is created', function () {
    $store = (new CreateStore)(CreateStoreData::from([
        'name' => 'Sequence Test Store',
        'timezone' => 'UTC',
    ]));

    expect(DocumentSequence::query()
        ->where('store_id', $store->id)
        ->where('document_type', 'invoice')
        ->value('prefix'))->toBe('INV-')
        ->and(DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', InvoiceType::Deposit->documentSequenceType())
            ->value('prefix'))->toBe('DEP-')
        ->and(DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', InvoiceType::Proforma->documentSequenceType())
            ->value('prefix'))->toBe('PRO-');
});

it('allocates standalone document numbers sequentially', function () {
    $opportunity = giCreateOrderOpportunity();
    app(DocumentTemplateSeeder::class)->seedForStore($opportunity->store);

    $first = app(GenerateDocument::class)(new GenerateDocumentData(
        document_type: 'quote',
        source_id: $opportunity->id,
        output_format: DocumentOutputFormat::Pdf,
    ));

    $second = app(GenerateDocument::class)(new GenerateDocumentData(
        document_type: 'quote',
        source_id: $opportunity->id,
        output_format: DocumentOutputFormat::Pdf,
    ));

    expect($first->document_number)->not->toBe($second->document_number)
        ->and($first->document_number)->toStartWith('QUO-')
        ->and($second->document_number)->toStartWith('QUO-');
});

it('covers every core document template type in the default prefix map', function () {
    foreach (SystemDocumentTemplates::CORE_TYPES as $documentType) {
        expect(DocumentSequenceDefaults::prefixFor($documentType))->not->toBe('');
    }
});

it('auto-provisions missing sequences through the generator without throwing', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);

    expect(fn () => app(DocumentNumberGenerator::class)->next($store->id, 'delivery_note'))
        ->not->toThrow(Throwable::class);

    expect(DocumentSequence::query()
        ->where('store_id', $store->id)
        ->where('document_type', 'delivery_note')
        ->value('prefix'))->toBe('DN-');
});
