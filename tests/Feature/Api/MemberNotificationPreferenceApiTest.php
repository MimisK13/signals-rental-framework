<?php

use App\Enums\NotificationAudience;
use App\Models\MemberStore;
use App\Models\NotificationPreference;
use App\Models\NotificationSetting;
use App\Models\NotificationType;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->storeA = Store::factory()->create();
    $this->storeB = Store::factory()->create();

    $this->manager = User::factory()->create(['is_owner' => false, 'is_admin' => false]);
    $this->manager->givePermissionTo(['members.view', 'members.edit']);
    MemberStore::create([
        'member_id' => $this->manager->member_id,
        'store_id' => $this->storeA->id,
        'created_at' => now(),
    ]);

    $this->targetUser = User::factory()->create();
    $this->targetMember = $this->targetUser->member;
    expect($this->targetMember)->not->toBeNull();

    MemberStore::create([
        'member_id' => $this->targetMember->id,
        'store_id' => $this->storeA->id,
        'created_at' => now(),
    ]);

    $this->otherStoreUser = User::factory()->create();
    $this->otherStoreMember = $this->otherStoreUser->member;
    expect($this->otherStoreMember)->not->toBeNull();

    MemberStore::create([
        'member_id' => $this->otherStoreMember->id,
        'store_id' => $this->storeB->id,
        'created_at' => now(),
    ]);

    $this->type = NotificationType::factory()->create([
        'audience' => NotificationAudience::Internal,
        'default_channels' => ['in_app', 'email'],
        'available_channels' => ['in_app', 'email'],
    ]);

    NotificationSetting::factory()->create([
        'notification_type_id' => $this->type->id,
        'channels' => ['database', 'mail'],
        'is_enabled' => true,
    ]);
});

/**
 * @param  list<string>  $abilities
 */
function memberNotificationPreferenceToken(User $user, array $abilities = ['notification-preferences:read', 'notification-preferences:write']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

describe('GET /api/v1/members/{member}/notification_preferences', function () {
    it('returns preferences for a member in scope', function () {
        $token = memberNotificationPreferenceToken($this->manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$this->targetMember->id}/notification_preferences")
            ->assertOk();

        $preferencePayload = collect($response->json('notification_preferences'))
            ->firstWhere('notification_type_id', $this->type->id);

        expect($preferencePayload)->not->toBeNull()
            ->and($preferencePayload['notification_type_id'])->toBe($this->type->id);
    });

    it('requires notification-preferences:read ability', function () {
        $token = $this->manager->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$this->targetMember->id}/notification_preferences")
            ->assertForbidden();
    });

    it('returns not found for a member outside store scope', function () {
        $token = memberNotificationPreferenceToken($this->manager);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$this->otherStoreMember->id}/notification_preferences")
            ->assertNotFound();
    });
});

describe('PUT /api/v1/members/{member}/notification_preferences', function () {
    it('updates member preferences for an in-scope member', function () {
        $token = memberNotificationPreferenceToken($this->manager);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/members/{$this->targetMember->id}/notification_preferences", [
                'preferences' => [
                    [
                        'notification_type_id' => $this->type->id,
                        'channels' => ['in_app'],
                        'is_muted' => false,
                    ],
                ],
            ])
            ->assertOk();

        $preferencePayload = collect($response->json('notification_preferences'))
            ->firstWhere('notification_type_id', $this->type->id);

        expect($preferencePayload['channels'])->toBe(['in_app']);

        $preference = NotificationPreference::query()
            ->where('user_id', $this->targetUser->id)
            ->where('notification_type_id', $this->type->id)
            ->first();

        expect($preference)->not->toBeNull()
            ->and($preference->channels)->toBe(['in_app']);
    });

    it('requires notification-preferences:write ability', function () {
        $token = $this->manager->createToken('test', ['notification-preferences:read', 'members.edit'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/members/{$this->targetMember->id}/notification_preferences", [
                'preferences' => [
                    [
                        'notification_type_id' => $this->type->id,
                        'is_muted' => true,
                    ],
                ],
            ])
            ->assertForbidden();
    });
});
