<?php

use App\Models\CommunicationLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

/**
 * @param  list<string>  $abilities
 */
function communicationLogApiToken(User $user, array $abilities = ['communications:read']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

describe('GET /api/v1/communication_log', function () {
    it('lists communication logs with pagination meta', function () {
        CommunicationLog::factory()->count(2)->create();
        $this->owner->givePermissionTo('notifications.manage');
        $token = communicationLogApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/communication_log')
            ->assertOk()
            ->assertJsonStructure([
                'communication_logs' => [
                    '*' => [
                        'id',
                        'notification_type_key',
                        'channel',
                        'status',
                        'subject_type',
                        'subject_id',
                        'created_at',
                    ],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 2);
    });

    it('filters by status using Ransack', function () {
        CommunicationLog::factory()->create(['status' => 'queued']);
        CommunicationLog::factory()->sent()->create();
        $this->owner->givePermissionTo('notifications.manage');
        $token = communicationLogApiToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/communication_log?q[status_eq]=sent')
            ->assertOk();

        expect($response->json('communication_logs'))->toHaveCount(1)
            ->and($response->json('communication_logs.0.status'))->toBe('sent');
    });

    it('requires communications:read ability', function () {
        $this->owner->givePermissionTo('notifications.manage');
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/communication_log')
            ->assertForbidden();
    });
});

describe('GET /api/v1/communication_log/{id}', function () {
    it('shows a single communication log entry', function () {
        $log = CommunicationLog::factory()->create(['status' => 'delivered']);
        $this->owner->givePermissionTo('notifications.manage');
        $token = communicationLogApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/communication_log/{$log->id}")
            ->assertOk()
            ->assertJsonPath('communication_log.id', $log->id)
            ->assertJsonPath('communication_log.status', 'delivered');
    });

    it('requires communications:read ability', function () {
        $log = CommunicationLog::factory()->create();
        $this->owner->givePermissionTo('notifications.manage');
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/communication_log/{$log->id}")
            ->assertForbidden();
    });
});
