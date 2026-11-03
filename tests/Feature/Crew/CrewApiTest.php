<?php

declare(strict_types=1);

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Members\Models\MemberStore;
use App\Domain\Projects\Models\Project;
use App\Domain\Stores\Models\Store;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    settings()->set('modules.crew', true, 'boolean');
});

it('returns 401 without a token', function (): void {
    $this->getJson('/api/v1/crew/members')->assertUnauthorized();
});

it('requires the crew token ability even when permissions are granted', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['crew.access', 'crew.view']);
    $token = $user->createToken('crew-test', ['projects:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/crew/members')->assertForbidden();
});

it('requires resource permission as well as a crew token', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('crew.access');
    $token = $user->createToken('crew-test', ['crew:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/crew/members')->assertForbidden();
});

it('hides endpoints while the crew module is disabled', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['crew.access', 'crew.view']);
    $token = $user->createToken('crew-test', ['crew:read'])->plainTextToken;
    settings()->set('modules.crew', false, 'boolean');

    $this->withToken($token)->getJson('/api/v1/crew/members')->assertNotFound();
});

it('creates a crew person through the API and audits once with an idempotency key', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['crew.access', 'crew.create']);
    $token = $user->createToken('crew-test', ['crew:write'])->plainTextToken;
    $payload = ['name' => 'API Crew', 'engagement_type' => 'freelancer'];

    $response = $this->withToken($token)->withHeader('Idempotency-Key', 'crew-person-one')->postJson('/api/v1/crew/members', $payload);
    $response->assertCreated()->assertJsonPath('member.name', 'API Crew');
    $this->withToken($token)->withHeader('Idempotency-Key', 'crew-person-one')->postJson('/api/v1/crew/members', $payload)->assertCreated()->assertJsonPath('member.id', $response->json('member.id'));

    $this->assertDatabaseCount('crew_members', 1);
    $this->assertDatabaseHas('action_logs', ['auditable_id' => $response->json('member.id'), 'action' => 'crew.member.created']);
});

it('returns field validation errors without persisting invalid input', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['crew.access', 'crew.create']);
    $token = $user->createToken('crew-test', ['crew:write'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/crew/members', ['name' => 'Alex', 'email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->assertDatabaseCount('crew_members', 0);
});

it('redacts sensitive crew fields from paginated API lists', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['crew.access', 'crew.view']);
    $token = $user->createToken('crew-test', ['crew:read'])->plainTextToken;
    CrewMember::factory()->create(['name' => 'Alex Crew', 'internal_notes' => 'Private note']);

    $this->withToken($token)->getJson('/api/v1/crew/members')->assertOk()->assertJsonPath('members.0.name', 'Alex Crew')->assertJsonPath('members.0.internal_notes', null)->assertJsonPath('meta.total', 1);
});

it('returns 404 for another stores request and excludes it from lists', function (): void {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $user = User::factory()->create();
    MemberStore::create(['member_id' => $user->member_id, 'store_id' => $storeA->id, 'created_at' => now()]);
    $user->givePermissionTo(['crew.access', 'crew.requests.view']);
    $token = $user->createToken('crew-test', ['crew:read'])->plainTextToken;
    $request = CrewRequest::factory()->forStore($storeB)->create();

    $this->withToken($token)->getJson('/api/v1/crew/requests/'.$request->id)->assertNotFound();
    $this->withToken($token)->getJson('/api/v1/crew/requests')->assertOk()->assertJsonPath('meta.total', 0);
});

it('allows report reads with a read token and denies compensation without rate access', function (): void {
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo(['crew.access', 'crew.reports.view']);
    $token = $user->createToken('crew-reports-test', ['crew:read'])->plainTextToken;
    $project = Project::factory()->create();

    $this->withToken($token)->getJson('/api/v1/crew/reports/utilisation?starts_at=2026-11-01&ends_at=2026-11-08')->assertOk()->assertJsonPath('utilisation.rows', []);
    $this->withToken($token)->getJson('/api/v1/crew/reports/cost-forecast?project_id='.$project->id)->assertForbidden();
});
