<?php

use App\Enums\ProjectStatus;
use App\Models\Member;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->token = $this->owner->createToken('project-crud', ['projects:read', 'projects:write'])->plainTextToken;
});

it('supports project API create, read, update, list, and delete', function (): void {
    $headers = ['Authorization' => "Bearer {$this->token}"];

    $created = $this->withHeaders($headers)
        ->postJson('/api/v1/projects', [
            'name' => 'API Project',
            'status' => ProjectStatus::Draft->value,
        ])
        ->assertCreated()
        ->assertJsonPath('project.name', 'API Project')
        ->assertJsonPath('project.status', ProjectStatus::Draft->value);

    $projectId = $created->json('project.id');

    $this->withHeaders($headers)
        ->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('projects.0.id', $projectId);

    $this->withHeaders($headers)
        ->getJson("/api/v1/projects/{$projectId}")
        ->assertOk()
        ->assertJsonPath('project.id', $projectId);

    $this->withHeaders($headers)
        ->patchJson("/api/v1/projects/{$projectId}", [
            'name' => 'Updated API Project',
            'status' => ProjectStatus::Active->value,
        ])
        ->assertOk()
        ->assertJsonPath('project.name', 'Updated API Project')
        ->assertJsonPath('project.status', ProjectStatus::Active->value);

    $this->withHeaders($headers)
        ->deleteJson("/api/v1/projects/{$projectId}")
        ->assertNoContent();

    expect(Project::withTrashed()->findOrFail($projectId)->trashed())->toBeTrue();
});

it('rejects invalid project payloads through the API', function (): void {
    $headers = ['Authorization' => "Bearer {$this->token}"];
    $venue = Member::factory()->venue()->create();

    $this->withHeaders($headers)
        ->postJson('/api/v1/projects', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->withHeaders($headers)
        ->postJson('/api/v1/projects', [
            'name' => 'Invalid API Project',
            'member_id' => $venue->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('member_id');
});

it('rejects invalid lifecycle transitions through the API', function (): void {
    $project = Project::factory()->create(['status' => ProjectStatus::Draft]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/projects/{$project->id}", [
            'status' => ProjectStatus::Completed->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($project->refresh()->status)->toBe(ProjectStatus::Draft);
});

it('enforces project API authentication and token abilities', function (): void {
    $this->getJson('/api/v1/projects')->assertUnauthorized();

    $readOnlyToken = $this->owner->createToken('project-read-only', ['projects:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$readOnlyToken}")
        ->postJson('/api/v1/projects', ['name' => 'Forbidden API Project'])
        ->assertForbidden();
});
