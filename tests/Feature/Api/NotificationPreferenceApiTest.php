<?php

use App\Enums\NotificationAudience;
use App\Models\NotificationPreference;
use App\Models\NotificationSetting;
use App\Models\NotificationType;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->type = NotificationType::factory()->create([
        'audience' => NotificationAudience::Internal,
        'default_channels' => ['in_app', 'email'],
        'available_channels' => ['in_app', 'email'],
    ]);
});

/**
 * @param  list<string>  $abilities
 */
function userNotificationPreferenceToken(User $user, array $abilities = ['notification-preferences:read', 'notification-preferences:write']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

describe('GET /api/v1/notification_preferences', function () {
    it('returns the authenticated user notification preferences', function () {
        NotificationSetting::factory()->create([
            'notification_type_id' => $this->type->id,
            'channels' => ['database', 'mail'],
            'is_enabled' => true,
        ]);

        $token = userNotificationPreferenceToken($this->user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notification_preferences')
            ->assertOk()
            ->assertJsonStructure([
                'notification_preferences' => [
                    '*' => [
                        'notification_type_id',
                        'notification_type_key',
                        'category',
                        'name',
                        'tenant_channels',
                        'channels',
                        'is_muted',
                    ],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        $preference = collect($response->json('notification_preferences'))
            ->firstWhere('notification_type_id', $this->type->id);

        expect($preference)->not->toBeNull()
            ->and($preference['notification_type_id'])->toBe($this->type->id);
    });

    it('requires notification-preferences:read ability', function () {
        $token = $this->user->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notification_preferences')
            ->assertForbidden();
    });
});

describe('PUT /api/v1/notification_preferences', function () {
    it('bulk updates and returns preferences round-trip', function () {
        NotificationSetting::factory()->create([
            'notification_type_id' => $this->type->id,
            'channels' => ['database', 'mail'],
            'is_enabled' => true,
        ]);

        $token = userNotificationPreferenceToken($this->user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/notification_preferences', [
                'preferences' => [
                    [
                        'notification_type_id' => $this->type->id,
                        'channels' => ['in_app'],
                        'is_muted' => false,
                    ],
                ],
            ])
            ->assertOk();

        $preference = collect($response->json('notification_preferences'))
            ->firstWhere('notification_type_id', $this->type->id);

        expect($preference['channels'])->toBe(['in_app'])
            ->and($preference['is_muted'])->toBeFalse();

        $preference = NotificationPreference::query()
            ->where('user_id', $this->user->id)
            ->where('notification_type_id', $this->type->id)
            ->first();

        expect($preference)->not->toBeNull()
            ->and($preference->channels)->toBe(['in_app']);
    });

    it('requires notification-preferences:write ability', function () {
        $token = $this->user->createToken('test', ['notification-preferences:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/notification_preferences', [
                'preferences' => [
                    [
                        'notification_type_id' => $this->type->id,
                        'channels' => ['in_app'],
                        'is_muted' => false,
                    ],
                ],
            ])
            ->assertForbidden();
    });
});
