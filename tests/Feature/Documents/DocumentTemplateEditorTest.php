<?php

use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
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
    $this->actingAs($this->user);

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

it('renders the document template editor with three panels', function () {
    $this->get(route('admin.document-templates.edit', $this->template))
        ->assertOk()
        ->assertSee('Field browser')
        ->assertSee('Live preview')
        ->assertSee('data-testid="codemirror-host"', false);
});

it('loads template content and generates a preview', function () {
    Volt::test('document-templates.edit', ['documentTemplate' => $this->template])
        ->assertSet('bodyHtml', $this->template->body_html)
        ->assertSet('previewError', '')
        ->assertSet('previewHtml', fn (string $html): bool => str_contains($html, 'Sample Line Item'));
});

it('inserts scalar and collection snippets into the active editor tab', function () {
    Volt::test('document-templates.edit', ['documentTemplate' => $this->template])
        ->call('insertField', 'opportunity.subject', 'string')
        ->assertSet('bodyHtml', fn (string $html): bool => str_contains($html, '{{ $opportunity->subject }}'))
        ->call('insertField', 'items', 'collection')
        ->assertSet('bodyHtml', fn (string $html): bool => str_contains($html, '@foreach ($items as $item)'));
});

it('saves edits through the update action and bumps the version', function () {
    $originalVersion = $this->template->version;

    Volt::test('document-templates.edit', ['documentTemplate' => $this->template])
        ->set('bodyHtml', '<p>Edited body {{ $opportunity->subject }}</p>')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('document-template-saved');

    $this->template->refresh();

    expect($this->template->version)->toBe($originalVersion + 1)
        ->and($this->template->body_html)->toContain('Edited body');
});

it('restores a previous version from the editor', function () {
    $originalBody = $this->template->body_html;

    Volt::test('document-templates.edit', ['documentTemplate' => $this->template])
        ->set('bodyHtml', '<p>Version two body</p>')
        ->call('save');

    $version = DocumentTemplateVersion::query()
        ->where('document_template_id', $this->template->id)
        ->where('body_html', $originalBody)
        ->firstOrFail();

    Volt::test('document-templates.edit', ['documentTemplate' => $this->template->fresh()])
        ->set('bodyHtml', '<p>Temporary change</p>')
        ->call('restoreVersion', $version->id)
        ->assertDispatched('document-template-version-restored')
        ->assertSet('bodyHtml', $originalBody);
});

it('forbids users without document template permissions', function () {
    $viewer = User::factory()->create(['is_owner' => false, 'is_active' => true]);
    $this->actingAs($viewer);

    $this->get(route('admin.document-templates.edit', $this->template))
        ->assertForbidden();
});

it('shows sandbox render errors inline instead of failing the request', function () {
    Volt::test('document-templates.edit', ['documentTemplate' => $this->template])
        ->set('bodyHtml', "@foreach (DB::table('users')->get() as \$user){{ \$user->id }}@endforeach")
        ->call('refreshPreview')
        ->assertSet('previewHtml', '')
        ->assertSet('previewError', fn (string $error): bool => str_contains($error, 'Scope resolution'));
});
