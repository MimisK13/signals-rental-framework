<?php

use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Models\User;
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

it('forbids users without document template permissions', function () {
    $viewer = User::factory()->create(['is_owner' => false, 'is_active' => true]);
    $this->actingAs($viewer);

    $this->get(route('admin.document-templates.index'))->assertForbidden();
});
