<?php

use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Models\User;
use App\Services\Documents\SystemDocumentTemplates;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->owner()->create();
    $this->user->givePermissionTo(['document_templates.view', 'document_templates.edit']);
    $this->actingAs($this->user);

    $this->store = Store::factory()->create();
    app(DocumentTemplateSeeder::class)->seedForStore($this->store);
});

it('renders grouped document templates', function () {
    $this->get(route('admin.document-templates.index'))
        ->assertOk()
        ->assertSee('Document Templates')
        ->assertSee('Standard Quote');
});

it('duplicates a template from the index page', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'quote')
        ->firstOrFail();

    Volt::test('document-templates.index')
        ->call('duplicateTemplate', $template->id);

    expect(DocumentTemplate::query()->where('store_id', $this->store->id)->where('slug', 'like', '%copy%')->exists())
        ->toBeTrue();
});

it('creates a template from an existing template and redirects to the editor', function () {
    $source = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'quote')
        ->firstOrFail();

    Volt::test('document-templates.index')
        ->set('createStartingPoint', 'duplicate')
        ->set('createName', 'Branched quote')
        ->set('createStoreId', (string) $this->store->id)
        ->set('createSourceTemplateId', (string) $source->id)
        ->call('createTemplate')
        ->assertRedirect(route('admin.document-templates.edit', DocumentTemplate::query()->where('name', 'Branched quote')->firstOrFail()));

    $created = DocumentTemplate::query()->where('name', 'Branched quote')->firstOrFail();

    expect($created->body_html)->toBe($source->body_html)
        ->and($created->header_html)->toBe($source->header_html)
        ->and($created->footer_html)->toBe($source->footer_html)
        ->and($created->styles_css)->toBe($source->styles_css);
});

it('creates a template from a system scaffold and redirects to the editor', function () {
    $definition = SystemDocumentTemplates::definition('invoice');

    Volt::test('document-templates.index')
        ->set('createStartingPoint', 'scaffold')
        ->set('createName', 'Custom invoice')
        ->set('createStoreId', (string) $this->store->id)
        ->set('createDocumentType', 'invoice')
        ->call('createTemplate')
        ->assertRedirect(route('admin.document-templates.edit', DocumentTemplate::query()->where('name', 'Custom invoice')->firstOrFail()));

    $created = DocumentTemplate::query()->where('name', 'Custom invoice')->firstOrFail();

    expect($created->body_html)->toBe($definition['body_html'])
        ->and($created->header_html)->toBe($definition['header_html'])
        ->and($created->footer_html)->toBe($definition['footer_html'])
        ->and($created->styles_css)->toBe($definition['styles_css'])
        ->and($created->document_type)->toBe('invoice');
});

it('creates a completely empty template and redirects to the editor', function () {
    Volt::test('document-templates.index')
        ->set('createStartingPoint', 'empty')
        ->set('createName', 'Empty quote')
        ->set('createStoreId', (string) $this->store->id)
        ->set('createDocumentType', 'quote')
        ->call('createTemplate')
        ->assertRedirect(route('admin.document-templates.edit', DocumentTemplate::query()->where('name', 'Empty quote')->firstOrFail()));

    $created = DocumentTemplate::query()->where('name', 'Empty quote')->firstOrFail();

    expect($created->body_html)->toBe('')
        ->and($created->header_html)->toBe('')
        ->and($created->footer_html)->toBe('')
        ->and($created->styles_css)->toBe('')
        ->and($created->document_type)->toBe('quote');
});

it('forbids creating templates without edit permission', function () {
    $viewer = User::factory()->create(['is_owner' => false, 'is_active' => true]);
    $viewer->givePermissionTo('document_templates.view');
    $this->actingAs($viewer);

    Volt::test('document-templates.index')
        ->set('createStartingPoint', 'empty')
        ->set('createName', 'Blocked template')
        ->set('createStoreId', (string) $this->store->id)
        ->set('createDocumentType', 'quote')
        ->call('createTemplate')
        ->assertForbidden();
});

it('forbids users without document template permissions', function () {
    $viewer = User::factory()->create(['is_owner' => false, 'is_active' => true]);
    $this->actingAs($viewer);

    $this->get(route('admin.document-templates.index'))->assertForbidden();
});
