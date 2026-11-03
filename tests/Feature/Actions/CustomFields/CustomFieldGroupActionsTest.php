<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\CustomFields\Actions\CreateCustomFieldGroup;
use App\Domain\CustomFields\Actions\DeleteCustomFieldGroup;
use App\Domain\CustomFields\Actions\UpdateCustomFieldGroup;
use App\Domain\CustomFields\Data\CreateCustomFieldGroupData;
use App\Domain\CustomFields\Data\UpdateCustomFieldGroupData;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a custom field group', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreateCustomFieldGroupData::from([
        'name' => 'General Info',
        'description' => 'General information fields',
        'sort_order' => 1,
    ]);

    $result = (new CreateCustomFieldGroup)($data);

    expect($result->name)->toBe('General Info');
    expect($result->description)->toBe('General information fields');
    expect($result->sort_order)->toBe(1);
    expect(CustomFieldGroup::where('name', 'General Info')->exists())->toBeTrue();

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a custom field group', function () {
    Event::fake([AuditableEvent::class]);

    $group = CustomFieldGroup::factory()->create(['name' => 'Old Name']);

    $data = UpdateCustomFieldGroupData::from(['name' => 'New Name']);

    $result = (new UpdateCustomFieldGroup)($group, $data);

    expect($result->name)->toBe('New Name');

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes a custom field group', function () {
    Event::fake([AuditableEvent::class]);

    $group = CustomFieldGroup::factory()->create();

    (new DeleteCustomFieldGroup)($group);

    expect(CustomFieldGroup::find($group->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('rejects unauthorized custom field group creation', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $data = CreateCustomFieldGroupData::from(['name' => 'Unauthorized']);

    (new CreateCustomFieldGroup)($data);
})->throws(AuthorizationException::class);
