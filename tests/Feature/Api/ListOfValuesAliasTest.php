<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('List of Values alias (list_names)', function () {
    it('lists all list names via list_of_values endpoint', function () {
        ListName::factory()->count(2)->create();
        $token = $this->owner->createToken('test', ['static-data:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/list_of_values')
            ->assertOk()
            ->assertJsonStructure([
                'list_names' => [
                    '*' => ['id', 'name', 'is_system', 'created_at'],
                ],
                'meta',
            ]);
    });

    it('creates a list name via list_of_values endpoint', function () {
        $token = $this->owner->createToken('test', ['static-data:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/list_of_values', [
                'name' => 'Priority Level',
            ])
            ->assertCreated()
            ->assertJsonPath('list_name.name', 'Priority Level');
    });

    it('shows a single list name via list_of_values endpoint', function () {
        $listName = ListName::factory()->create();
        ListValue::factory()->for($listName)->count(3)->create();
        $token = $this->owner->createToken('test', ['static-data:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/list_of_values/{$listName->id}")
            ->assertOk()
            ->assertJsonPath('list_name.name', $listName->name);

        expect($response->json('list_name.values'))->toHaveCount(3);
    });

    it('updates a list name via list_of_values endpoint', function () {
        $listName = ListName::factory()->create(['name' => 'Old']);
        $token = $this->owner->createToken('test', ['static-data:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/list_of_values/{$listName->id}", [
                'name' => 'Renamed',
            ])
            ->assertOk()
            ->assertJsonPath('list_name.name', 'Renamed');
    });

    it('deletes a list name via list_of_values endpoint', function () {
        $listName = ListName::factory()->create();
        $token = $this->owner->createToken('test', ['static-data:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/list_of_values/{$listName->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('list_names', ['id' => $listName->id]);
    });

    it('returns 404 when deleting another lists value through the alias', function () {
        $listName = ListName::factory()->create();
        $otherList = ListName::factory()->create();
        $value = ListValue::factory()->for($otherList)->create();
        $token = $this->owner->createToken('test', ['static-data:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/list_of_values/{$listName->id}/list_values/{$value->id}")
            ->assertNotFound();

        $this->assertModelExists($value);
    });
});
