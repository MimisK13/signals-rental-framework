<?php

use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\User;
use App\Services\Documents\EntityNumberSchemeCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->store = Store::factory()->create(['timezone' => 'UTC', 'is_default' => true]);
    $this->secondStore = Store::factory()->create(['timezone' => 'UTC', 'name' => 'Second Store']);
});

function numberingAdminUser(): User
{
    $user = User::factory()->admin()->create();
    $user->givePermissionTo(['numbering.view', 'numbering.edit', 'document_templates.edit']);

    return $user;
}

it('renders all eight entity types grouped by store and global', function () {
    $this->actingAs(numberingAdminUser());

    $response = $this->get(route('admin.settings.numbering'))
        ->assertOk()
        ->assertSee('Numbering')
        ->assertSee('Global')
        ->assertSee('Member and payment numbers are shared across all stores.');

    foreach (EntityNumberSchemeCatalog::STORE_SCOPED_TYPES as $documentType) {
        $response->assertSee(EntityNumberSchemeCatalog::labelFor($documentType));
    }

    foreach (EntityNumberSchemeCatalog::GLOBAL_TYPES as $documentType) {
        $response->assertSee(EntityNumberSchemeCatalog::labelFor($documentType));
    }

    expect(substr_count($response->getContent(), EntityNumberSchemeCatalog::labelFor('opportunity')))
        ->toBe(2);
});

it('shows default previews when sequence rows are missing', function () {
    $this->actingAs(numberingAdminUser());

    Volt::test('admin.settings.numbering')
        ->assertSet('defaultStoreId', $this->store->id);

    $this->get(route('admin.settings.numbering'))
        ->assertOk()
        ->assertSee('INV-')
        ->assertSee('MEM-');
});

it('persists edits and updates the list preview after save', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-15 12:00:00', 'UTC'));

    $this->actingAs(numberingAdminUser());

    DocumentSequence::factory()->create([
        'store_id' => $this->store->id,
        'document_type' => 'invoice_standard',
        'prefix' => 'INV-',
        'include_year' => true,
        'include_month' => false,
        'padding' => 4,
        'next_number' => 10,
        'is_entity_number' => true,
    ]);

    Volt::test('admin.settings.numbering')
        ->call('openEdit', $this->store->id, 'invoice_standard')
        ->set('formPrefix', 'IN-CX-')
        ->set('formSuffix', '')
        ->set('formIncludeYear', false)
        ->set('formIncludeMonth', false)
        ->set('formNextNumber', 1001)
        ->set('formPadding', 9)
        ->assertSet('formPreview', 'IN-CX-000001001')
        ->call('saveSequence')
        ->assertHasNoErrors()
        ->assertSet('showEditModal', false);

    $sequence = DocumentSequence::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'invoice_standard')
        ->first();

    expect($sequence)->not->toBeNull()
        ->and($sequence->prefix)->toBe('IN-CX-')
        ->and($sequence->padding)->toBe(9)
        ->and($sequence->next_number)->toBe(1001)
        ->and($sequence->include_year)->toBeFalse();

    Volt::test('admin.settings.numbering')
        ->assertSee('IN-CX-000001001');

    CarbonImmutable::setTestNow();
});

it('provisions a missing sequence row on first save', function () {
    $this->actingAs(numberingAdminUser());

    expect(DocumentSequence::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'member')
        ->exists())->toBeFalse();

    Volt::test('admin.settings.numbering')
        ->call('openEdit', $this->store->id, 'member')
        ->set('formPrefix', 'MEM-CX-')
        ->set('formNextNumber', 42)
        ->set('formPadding', 5)
        ->set('formIncludeYear', false)
        ->set('formIncludeMonth', false)
        ->call('saveSequence')
        ->assertHasNoErrors();

    $sequence = DocumentSequence::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'member')
        ->first();

    expect($sequence)->not->toBeNull()
        ->and($sequence->prefix)->toBe('MEM-CX-')
        ->and($sequence->next_number)->toBe(42)
        ->and($sequence->is_entity_number)->toBeTrue();
});

it('rejects padding greater than twelve digits', function () {
    $this->actingAs(numberingAdminUser());

    DocumentSequence::factory()->create([
        'store_id' => $this->store->id,
        'document_type' => 'credit_note',
        'is_entity_number' => true,
    ]);

    Volt::test('admin.settings.numbering')
        ->call('openEdit', $this->store->id, 'credit_note')
        ->set('formPadding', 13)
        ->call('saveSequence')
        ->assertHasErrors(['formPadding']);
});

it('rejects a next number that does not fit the padding', function () {
    $this->actingAs(numberingAdminUser());

    DocumentSequence::factory()->create([
        'store_id' => $this->store->id,
        'document_type' => 'purchase_order',
        'padding' => 4,
        'next_number' => 10,
        'is_entity_number' => true,
    ]);

    Volt::test('admin.settings.numbering')
        ->call('openEdit', $this->store->id, 'purchase_order')
        ->set('formNextNumber', 10000)
        ->set('formPadding', 4)
        ->call('saveSequence')
        ->assertHasErrors(['formNextNumber']);
});

it('surfaces backwards-set collision errors on the form', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-15 12:00:00', 'UTC'));

    $this->actingAs(numberingAdminUser());

    DocumentSequence::factory()->create([
        'store_id' => $this->store->id,
        'document_type' => 'invoice_standard',
        'prefix' => 'INV-',
        'include_year' => true,
        'include_month' => false,
        'padding' => 4,
        'next_number' => 100,
        'is_entity_number' => true,
    ]);

    Invoice::factory()->create([
        'store_id' => $this->store->id,
        'type' => 'standard',
        'number' => 'INV-2026-0050',
    ]);

    Volt::test('admin.settings.numbering')
        ->call('openEdit', $this->store->id, 'invoice_standard')
        ->set('formNextNumber', 50)
        ->call('saveSequence')
        ->assertHasErrors(['formNextNumber'])
        ->assertSee('INV-2026-0050');

    CarbonImmutable::setTestNow();
});

it('returns 403 for users without numbering view permission', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('admin.settings.numbering'))
        ->assertForbidden();
});

it('hides the numbering nav item without numbering view permission', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $this->get(route('admin.index'))
        ->assertOk()
        ->assertDontSee('Numbering');
});

it('shows the numbering nav item for users with numbering view permission', function () {
    $user = User::factory()->admin()->create();
    $user->givePermissionTo('numbering.view');
    $this->actingAs($user);

    $this->get(route('admin.index'))
        ->assertOk()
        ->assertSee('Numbering');
});

it('forbids save when the user can view but not edit numbering schemes', function () {
    $viewer = User::factory()->admin()->create();
    $viewer->givePermissionTo(['numbering.view', 'document_templates.edit']);

    DocumentSequence::factory()->create([
        'store_id' => $this->store->id,
        'document_type' => 'opportunity',
        'is_entity_number' => true,
    ]);

    Volt::actingAs($viewer)
        ->test('admin.settings.numbering')
        ->call('openEdit', $this->store->id, 'opportunity')
        ->set('formPrefix', 'OPP-')
        ->call('saveSequence')
        ->assertForbidden();
});

it('grants operations manager view but not edit permissions for numbering', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(Role::findByName('Operations Manager', 'web'));

    expect($manager->can('numbering.view'))->toBeTrue()
        ->and($manager->can('numbering.edit'))->toBeFalse();
});

it('updates live preview as form fields change', function () {
    $this->actingAs(numberingAdminUser());

    Volt::test('admin.settings.numbering')
        ->call('openEdit', $this->store->id, 'invoice_standard')
        ->set('formPrefix', 'IN-CX-')
        ->set('formSuffix', '')
        ->set('formIncludeYear', false)
        ->set('formIncludeMonth', false)
        ->set('formNextNumber', 1001)
        ->set('formPadding', 9)
        ->assertSet('formPreview', 'IN-CX-000001001');
});
