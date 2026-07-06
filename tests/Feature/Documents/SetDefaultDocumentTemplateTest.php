<?php

use App\Actions\Documents\SetDefaultDocumentTemplate;
use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('clears sibling defaults and sets the chosen template in one transaction', function () {
    $store = Store::factory()->create();
    app(DocumentTemplateSeeder::class)->seedForStore($store);

    $systemQuote = DocumentTemplate::query()
        ->where('store_id', $store->id)
        ->where('document_type', 'quote')
        ->where('is_system', true)
        ->firstOrFail();

    $custom = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'slug' => 'alt-quote',
        'is_default' => false,
        'is_system' => false,
    ]);

    (new SetDefaultDocumentTemplate)($custom);

    expect($systemQuote->refresh()->is_default)->toBeFalse()
        ->and($custom->refresh()->is_default)->toBeTrue();
});
