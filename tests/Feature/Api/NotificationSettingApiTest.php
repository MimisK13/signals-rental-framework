<?php

use App\Models\NotificationSetting;
use App\Models\NotificationType;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->owner()->create();
    $this->owner->givePermissionTo('notifications.manage');

    $this->type = NotificationType::factory()->create([
        'available_channels' => ['database', 'mail'],
        'default_channels' => ['database', 'mail'],
    ]);
});

/**
 * @param  list<string>  $abilities
 */
function notificationSettingApiToken(User $user, array $abilities = ['notification-settings:read', 'notification-settings:write']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

describe('GET /api/v1/notification_settings', function () {
    it('returns tenant notification settings for active types', function () {
        NotificationSetting::factory()->create([
            'notification_type_id' => $this->type->id,
            'channels' => ['database'],
            'is_enabled' => true,
        ]);

        $token = notificationSettingApiToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notification_settings')
            ->assertOk()
            ->assertJsonStructure([
                'notification_settings' => [
                    '*' => [
                        'notification_type_id',
                        'notification_type_key',
                        'category',
                        'name',
                        'is_enabled',
                        'channels',
                        'recipient_rules',
                    ],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        $setting = collect($response->json('notification_settings'))
            ->firstWhere('notification_type_id', $this->type->id);

        expect($setting)->not->toBeNull()
            ->and($setting['channels'])->toBe(['database']);
    });

    it('requires notification-settings:read ability', function () {
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notification_settings')
            ->assertForbidden();
    });
});

describe('PUT /api/v1/notification_settings', function () {
    it('bulk updates settings and returns the round-trip payload', function () {
        NotificationSetting::factory()->create([
            'notification_type_id' => $this->type->id,
            'channels' => ['database', 'mail'],
            'is_enabled' => true,
        ]);

        $token = notificationSettingApiToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/notification_settings', [
                'notification_settings' => [
                    [
                        'notification_type_id' => $this->type->id,
                        'is_enabled' => true,
                        'channels' => ['database'],
                        'recipient_rules' => [
                            ['type' => 'subject_owner'],
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $settingPayload = collect($response->json('notification_settings'))
            ->firstWhere('notification_type_id', $this->type->id);

        expect($settingPayload['channels'])->toBe(['database'])
            ->and($settingPayload['recipient_rules'][0]['type'])->toBe('subject_owner');

        $setting = NotificationSetting::query()->where('notification_type_id', $this->type->id)->first();

        expect($setting)->not->toBeNull()
            ->and($setting->channels)->toBe(['database']);
    });

    it('requires notification-settings:write ability', function () {
        $token = $this->owner->createToken('test', ['notification-settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/notification_settings', [
                'notification_settings' => [
                    [
                        'notification_type_id' => $this->type->id,
                        'channels' => ['database'],
                    ],
                ],
            ])
            ->assertForbidden();
    });
});
