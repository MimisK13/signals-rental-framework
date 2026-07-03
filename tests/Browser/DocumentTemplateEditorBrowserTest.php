<?php

use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Pest\Browser\Api\Webpage;

beforeEach(function () {
    if (! interface_exists(Webpage::class)) {
        $this->markTestSkipped('Install pestphp/pest-plugin-browser and Playwright to run browser smoke tests.');
    }

    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->actingAs(User::factory()->owner()->create());

    $this->store = Store::factory()->create();
    app(DocumentTemplateSeeder::class)->seedForStore($this->store);

    $this->template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'quote')
        ->firstOrFail();

    DocumentSequence::factory()->create([
        'store_id' => $this->store->id,
        'document_type' => 'quote',
        'prefix' => 'QUO-',
        'padding' => 4,
        'next_number' => 1,
    ]);
});

it('shows the three-panel editor and renders a preview iframe', function () {
    $page = visit(route('admin.document-templates.edit', $this->template));

    $page->assertSee('Field browser')
        ->assertSee('Live preview')
        ->assertPresent('[data-testid="field-browser"]')
        ->assertPresent('[data-testid="code-editor"]')
        ->assertPresent('[data-testid="preview-panel"]')
        ->assertPresent('[data-testid="preview-iframe"]')
        ->assertNoJavascriptErrors();
});
