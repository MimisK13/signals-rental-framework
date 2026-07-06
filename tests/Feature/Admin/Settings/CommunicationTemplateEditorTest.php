<?php

use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->owner()->create();
    $this->admin = User::factory()->admin()->create();
    $this->viewer = User::factory()->create(['is_owner' => false]);
    $this->store = Store::factory()->create();

    $this->seed(NotificationTypeSeeder::class);
    $type = NotificationType::query()->where('key', 'opportunity.confirmed')->firstOrFail();

    $this->template = CommunicationTemplate::factory()->create([
        'notification_type_id' => $type->id,
        'store_id' => $this->store->id,
        'channel' => 'email',
        'subject' => 'Booking confirmed: {{ opportunity.subject }}',
        'body' => 'Hello {{ contact.name }}, your booking **{{ opportunity.reference }}** is confirmed.',
    ]);
});

test('communication template editor renders with preview and field browser', function () {
    $this->actingAs($this->owner)
        ->get(route('admin.settings.communication-templates.edit', $this->template))
        ->assertOk()
        ->assertSee('Merge field browser')
        ->assertSee('Live preview')
        ->assertSee('Opportunity');
});

test('communication template editor is forbidden without notifications.manage', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.communication-templates.edit', $this->template))
        ->assertForbidden();
});

test('inserts merge fields into the template body', function () {
    Volt::actingAs($this->owner)
        ->test('admin.settings.communication-template-editor', ['communicationTemplate' => $this->template])
        ->call('insertField', 'opportunity.starts_at')
        ->assertSet('body', fn (string $body): bool => str_contains($body, '{{ opportunity.starts_at }}'));
});

test('renders live preview with sample data by default', function () {
    Volt::actingAs($this->owner)
        ->test('admin.settings.communication-template-editor', ['communicationTemplate' => $this->template])
        ->assertSet('previewMode', 'sample')
        ->assertSet('previewBody', fn (string $body): bool => str_contains($body, 'Alex Sample'));
});

test('renders live preview with a store-scoped opportunity', function () {
    $opportunity = Opportunity::factory()->create([
        'store_id' => $this->store->id,
        'subject' => 'Real Festival Gig',
        'reference' => 'OPP-REAL',
    ]);

    Volt::actingAs($this->owner)
        ->test('admin.settings.communication-template-editor', ['communicationTemplate' => $this->template])
        ->set('previewMode', 'entity')
        ->set('sourceOpportunityId', $opportunity->id)
        ->assertSet('previewBody', fn (string $body): bool => str_contains($body, 'OPP-REAL'));
});

test('cannot preview with an opportunity from another store', function () {
    $foreignStore = Store::factory()->create();
    $foreignOpportunity = Opportunity::factory()->create([
        'store_id' => $foreignStore->id,
        'subject' => 'Foreign Opportunity',
    ]);

    Volt::actingAs($this->owner)
        ->test('admin.settings.communication-template-editor', ['communicationTemplate' => $this->template])
        ->set('previewMode', 'entity')
        ->set('sourceOpportunityId', $foreignOpportunity->id)
        ->assertSet('previewBody', fn (string $body): bool => ! str_contains($body, 'Foreign Opportunity'));
});

test('saves template edits and bumps version', function () {
    $originalVersion = $this->template->version;

    Volt::actingAs($this->owner)
        ->test('admin.settings.communication-template-editor', ['communicationTemplate' => $this->template])
        ->set('body', 'Updated body for {{ contact.name }}')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('communication-template-saved');

    $this->template->refresh();

    expect($this->template->version)->toBe($originalVersion + 1)
        ->and($this->template->body)->toContain('Updated body');
});

test('unauthorized user cannot save template edits', function () {
    Volt::actingAs($this->admin)
        ->test('admin.settings.communication-template-editor', ['communicationTemplate' => $this->template])
        ->assertForbidden();
});

test('system templates cannot be saved but can be duplicated', function () {
    $systemTemplate = CommunicationTemplate::factory()->system()->create([
        'notification_type_id' => $this->template->notification_type_id,
        'store_id' => $this->store->id,
    ]);

    Volt::actingAs($this->owner)
        ->test('admin.settings.communication-template-editor', ['communicationTemplate' => $systemTemplate])
        ->set('body', 'Attempted edit')
        ->call('save')
        ->assertHasErrors('template');

    Volt::actingAs($this->owner)
        ->test('admin.settings.communication-template-editor', ['communicationTemplate' => $systemTemplate])
        ->call('duplicateTemplate')
        ->assertRedirect();

    expect(CommunicationTemplate::query()->where('is_system', false)->where('name', 'like', 'Copy of%')->exists())->toBeTrue();
});

test('restores a previous template version from the editor', function () {
    $originalBody = $this->template->body;

    Volt::actingAs($this->owner)
        ->test('admin.settings.communication-template-editor', ['communicationTemplate' => $this->template])
        ->set('body', 'Version two body')
        ->call('save');

    $version = CommunicationTemplateVersion::query()
        ->where('communication_template_id', $this->template->id)
        ->where('body', $originalBody)
        ->firstOrFail();

    Volt::actingAs($this->owner)
        ->test('admin.settings.communication-template-editor', ['communicationTemplate' => $this->template->fresh()])
        ->call('restoreVersion', $version->id)
        ->assertDispatched('communication-template-version-restored')
        ->assertSet('body', $originalBody);
});

test('sms template shows segment counter', function () {
    $smsTemplate = CommunicationTemplate::factory()->sms()->create([
        'notification_type_id' => $this->template->notification_type_id,
        'store_id' => $this->store->id,
        'body' => str_repeat('A', 200),
    ]);

    $this->actingAs($this->owner)
        ->get(route('admin.settings.communication-templates.edit', $smsTemplate))
        ->assertOk()
        ->assertSee('segment');
});
