<?php

use App\Models\CommunicationTemplate;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Services\Documents\DocumentSequenceDefaults;
use App\Services\Documents\SystemDocumentTemplates;
use App\Services\Notifications\SystemCommunicationTemplates;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\StoreSeeder;

require_once __DIR__.'/../../Support/DemoSeederTestHelpers.php';

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $this->seed(StoreSeeder::class);
    $this->seed(NotificationTypeSeeder::class);
});

it('seeds document templates, communication templates, and sequences for demo stores', function () {
    seedDemoStores();

    $demoStores = Store::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->orderBy('name')
        ->get();

    expect($demoStores)->toHaveCount(3);

    foreach ($demoStores as $store) {
        expect(DocumentTemplate::query()->where('store_id', $store->id)->count())
            ->toBe(count(SystemDocumentTemplates::CORE_TYPES));

        expect(CommunicationTemplate::query()->where('store_id', $store->id)->count())
            ->toBe(count(SystemCommunicationTemplates::seededTypeKeys()));

        foreach (DocumentSequenceDefaults::seedableDocumentTypes() as $documentType) {
            expect(DocumentSequence::query()
                ->where('store_id', $store->id)
                ->where('document_type', $documentType)
                ->exists())->toBeTrue("Missing sequence [{$documentType}] for store {$store->name}");
        }
    }
});

it('is idempotent when demo store template seeding is re-run', function () {
    seedDemoStores();

    $store = Store::query()->where('name', 'London Warehouse')->firstOrFail();
    $documentCount = DocumentTemplate::query()->where('store_id', $store->id)->count();
    $communicationCount = CommunicationTemplate::query()->where('store_id', $store->id)->count();
    $sequenceCount = DocumentSequence::query()->where('store_id', $store->id)->count();

    seedDemoStores();

    expect(DocumentTemplate::query()->where('store_id', $store->id)->count())->toBe($documentCount)
        ->and(CommunicationTemplate::query()->where('store_id', $store->id)->count())->toBe($communicationCount)
        ->and(DocumentSequence::query()->where('store_id', $store->id)->count())->toBe($sequenceCount);
});
