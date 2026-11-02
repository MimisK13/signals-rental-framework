<?php

use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the lists page with list name', function () {
    $listName = ListName::factory()->create(['name' => 'Payment Terms']);

    $this->get(route('admin.settings.lists', $listName))
        ->assertOk()
        ->assertSee('Payment Terms');
});

it('lists values for a list name', function () {
    $listName = ListName::factory()->create();
    ListValue::factory()->forList($listName)->create(['name' => 'Net 30']);
    ListValue::factory()->forList($listName)->create(['name' => 'Net 60']);

    Livewire::test('admin.settings.lists', ['listName' => $listName])
        ->assertSee('Net 30')
        ->assertSee('Net 60');
});

it('renders the create value form', function () {
    $listName = ListName::factory()->create();

    $this->get(route('admin.settings.list-values.create', $listName))
        ->assertOk()
        ->assertSee('Add Value');
});

it('can create a value', function () {
    $listName = ListName::factory()->create();

    Livewire::test('admin.settings.list-value-form', ['listName' => $listName])
        ->set('name', 'New Value')
        ->set('sortOrder', 1)
        ->call('save');

    expect(ListValue::where('name', 'New Value')->where('list_name_id', $listName->id)->exists())->toBeTrue();
});

it('can edit a value', function () {
    $listName = ListName::factory()->create();
    $value = ListValue::factory()->forList($listName)->create(['name' => 'Old Value']);

    Livewire::test('admin.settings.list-value-form', ['listName' => $listName, 'listValue' => $value])
        ->assertSet('name', 'Old Value')
        ->set('name', 'Updated Value')
        ->call('save');

    expect($value->fresh()->name)->toBe('Updated Value');
});

it('returns 404 when editing a value through another list name', function () {
    $listName = ListName::factory()->create();
    $otherList = ListName::factory()->create();
    $value = ListValue::factory()->forList($otherList)->create();

    $this->get(route('admin.settings.list-values.edit', [$listName, $value]))
        ->assertNotFound();
});

it('can toggle active status', function () {
    $listName = ListName::factory()->create();
    $value = ListValue::factory()->forList($listName)->create(['is_active' => true]);

    Livewire::test('admin.settings.lists', ['listName' => $listName])
        ->call('toggleActive', $value->id);

    expect($value->fresh()->is_active)->toBeFalse();
});

it('can delete a non-system value', function () {
    $listName = ListName::factory()->create();
    $value = ListValue::factory()->forList($listName)->create(['name' => 'Deletable']);

    Livewire::test('admin.settings.lists', ['listName' => $listName])
        ->call('deleteValue', $value->id);

    expect(ListValue::where('name', 'Deletable')->exists())->toBeFalse();
});

it('cannot delete a system value', function () {
    $listName = ListName::factory()->create();
    $value = ListValue::factory()->forList($listName)->system()->create(['name' => 'System Value']);

    Livewire::test('admin.settings.lists', ['listName' => $listName])
        ->call('deleteValue', $value->id)
        ->assertHasErrors('delete');

    expect(ListValue::where('name', 'System Value')->exists())->toBeTrue();
});

it('returns 403 for non-admin users', function () {
    $listName = ListName::factory()->create();
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.lists', $listName))
        ->assertForbidden();
});
