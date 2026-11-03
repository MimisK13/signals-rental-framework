<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Lists\Actions\CreateListValue;
use App\Domain\Lists\Actions\DeleteListValue;
use App\Domain\Lists\Actions\UpdateListValue;
use App\Domain\Lists\Data\CreateListValueData;
use App\Domain\Lists\Data\UpdateListValueData;
use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a list value', function () {
    Event::fake([AuditableEvent::class]);

    $listName = ListName::factory()->create();

    $data = CreateListValueData::from([
        'list_name_id' => $listName->id,
        'name' => 'Mobile',
        'sort_order' => 1,
    ]);

    $result = (new CreateListValue)($data);

    expect($result->name)->toBe('Mobile');
    expect($result->list_name_id)->toBe($listName->id);
    expect($result->sort_order)->toBe(1);

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a list value', function () {
    Event::fake([AuditableEvent::class]);

    $value = ListValue::factory()->create(['name' => 'Old Value']);

    $data = UpdateListValueData::from(['name' => 'New Value']);

    $result = (new UpdateListValue)($value, $data);

    expect($result->name)->toBe('New Value');

    Event::assertDispatched(AuditableEvent::class);
});

it('rejects a parent from another list', function (): void {
    $listName = ListName::factory()->hierarchical()->create();
    $otherList = ListName::factory()->hierarchical()->create();
    $foreignParent = ListValue::factory()->forList($otherList)->create();

    expect(fn () => (new CreateListValue)(CreateListValueData::from([
        'list_name_id' => $listName->id,
        'name' => 'Invalid child',
        'parent_id' => $foreignParent->id,
    ])))->toThrow(ValidationException::class);

    $this->assertDatabaseMissing('list_values', [
        'list_name_id' => $listName->id,
        'name' => 'Invalid child',
    ]);
});

it('rejects self-parenting and descendant cycles', function (): void {
    $listName = ListName::factory()->hierarchical()->create();
    $root = ListValue::factory()->forList($listName)->create();
    $child = ListValue::factory()->forList($listName)->create(['parent_id' => $root->id]);
    $grandchild = ListValue::factory()->forList($listName)->create(['parent_id' => $child->id]);

    expect(fn () => (new UpdateListValue)($root, UpdateListValueData::from([
        'parent_id' => $root->id,
    ])))->toThrow(ValidationException::class);

    expect(fn () => (new UpdateListValue)($root, UpdateListValueData::from([
        'parent_id' => $grandchild->id,
    ])))->toThrow(ValidationException::class);

    expect($root->fresh()->parent_id)->toBeNull();
});

it('clears a parent when null is explicitly provided', function (): void {
    $listName = ListName::factory()->hierarchical()->create();
    $parent = ListValue::factory()->forList($listName)->create();
    $child = ListValue::factory()->forList($listName)->create(['parent_id' => $parent->id]);

    (new UpdateListValue)($child, UpdateListValueData::from(['parent_id' => null]));

    expect($child->fresh()->parent_id)->toBeNull();
});

it('deletes a list value', function () {
    Event::fake([AuditableEvent::class]);

    $value = ListValue::factory()->create();

    (new DeleteListValue)($value);

    expect(ListValue::find($value->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('records an action_logs row when a list value is created', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $listName = ListName::factory()->create();

    $result = (new CreateListValue)(CreateListValueData::from([
        'list_name_id' => $listName->id,
        'name' => 'Audited Value',
    ]));

    assertActionLogged('list_value.created', (new ListValue)->getMorphClass(), $result->id, $user->id);
});

it('records an action_logs row when a list value is updated', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $value = ListValue::factory()->create();

    (new UpdateListValue)($value, UpdateListValueData::from(['name' => 'Renamed Value']));

    assertActionLogged('list_value.updated', $value->getMorphClass(), $value->id, $user->id);
});

it('records an action_logs row when a list value is deleted', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $value = ListValue::factory()->create();

    (new DeleteListValue)($value);

    assertActionLogged('list_value.deleted', $value->getMorphClass(), $value->id, $user->id);
});

it('rejects unauthorized list value creation', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $listName = ListName::factory()->create();

    $data = CreateListValueData::from([
        'list_name_id' => $listName->id,
        'name' => 'Unauthorized',
    ]);

    (new CreateListValue)($data);
})->throws(AuthorizationException::class);
