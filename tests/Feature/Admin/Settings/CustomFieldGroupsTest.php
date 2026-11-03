<?php

use App\Domain\Administration\Models\User;
use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use App\Domain\CustomFields\Presentation\Livewire\GroupForm;
use App\Domain\CustomFields\Presentation\Livewire\GroupsIndex;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the custom field groups page', function () {
    $this->get(route('admin.settings.custom-field-groups'))
        ->assertOk()
        ->assertSee('Custom Field Groups');
});

it('lists custom field groups', function () {
    CustomFieldGroup::factory()->create(['name' => 'General Info']);
    CustomFieldGroup::factory()->create(['name' => 'Billing Details']);

    Livewire::test(GroupsIndex::class)
        ->assertSee('General Info')
        ->assertSee('Billing Details');
});

it('renders the create form', function () {
    $this->get(route('admin.settings.custom-field-groups.create'))
        ->assertOk()
        ->assertSee('Create Custom Field Group');
});

it('can create a group', function () {
    Livewire::test(GroupForm::class)
        ->set('name', 'Test Group')
        ->set('description', 'A test group')
        ->set('sortOrder', 5)
        ->call('save');

    expect(CustomFieldGroup::where('name', 'Test Group')->exists())->toBeTrue();
});

it('renders the edit form', function () {
    $group = CustomFieldGroup::factory()->create(['name' => 'Existing Group']);

    $this->get(route('admin.settings.custom-field-groups.edit', $group))
        ->assertOk()
        ->assertSee('Edit Custom Field Group');
});

it('can edit a group', function () {
    $group = CustomFieldGroup::factory()->create(['name' => 'Old Name']);

    Livewire::test(GroupForm::class, ['customFieldGroup' => $group])
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('save');

    expect($group->fresh()->name)->toBe('New Name');
});

it('validates required name', function () {
    Livewire::test(GroupForm::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('can delete a group without fields', function () {
    $group = CustomFieldGroup::factory()->create(['name' => 'Empty Group']);

    Livewire::test(GroupsIndex::class)
        ->call('deleteGroup', $group->id);

    expect(CustomFieldGroup::where('name', 'Empty Group')->exists())->toBeFalse();
});

it('cannot delete a group with fields', function () {
    $group = CustomFieldGroup::factory()->create(['name' => 'Has Fields']);
    CustomField::factory()->inGroup($group)->create();

    Livewire::test(GroupsIndex::class)
        ->call('deleteGroup', $group->id)
        ->assertHasErrors('delete');

    expect(CustomFieldGroup::where('name', 'Has Fields')->exists())->toBeTrue();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.custom-field-groups'))
        ->assertForbidden();
});
