<?php

use App\Actions\Notifications\SaveMemberNotificationPreferences;
use App\Data\Notifications\SaveMemberNotificationPreferencesData;
use App\Enums\NotificationAudience;
use App\Models\NotificationPreference;
use App\Models\NotificationSetting;
use App\Models\NotificationType;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    $this->type = NotificationType::factory()->create([
        'audience' => NotificationAudience::Internal,
        'default_channels' => ['in_app', 'email'],
        'available_channels' => ['in_app', 'email', 'slack'],
    ]);
});

test('my notifications page renders for any authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('settings.notifications'))
        ->assertOk()
        ->assertSee('Notifications')
        ->assertSee('Choose how you receive alerts')
        ->assertSee($this->type->name);
});

test('avatar menu does not include a separate my notifications link', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('My Notifications', false);
});

test('member can save muted preference', function () {
    $this->actingAs($this->user);

    Volt::test('settings.notifications')
        ->set("preferences.{$this->type->id}.is_muted", true)
        ->call('save')
        ->assertHasNoErrors();

    $preference = NotificationPreference::query()
        ->where('user_id', $this->user->id)
        ->where('notification_type_id', $this->type->id)
        ->first();

    expect($preference)->not->toBeNull()
        ->and($preference->is_muted)->toBeTrue();
});

test('member can narrow channels to tenant-enabled subset', function () {
    $this->actingAs($this->user);
    NotificationSetting::factory()->create([
        'notification_type_id' => $this->type->id,
        'channels' => ['in_app', 'email'],
        'is_enabled' => true,
    ]);

    Volt::test('settings.notifications')
        ->set("preferences.{$this->type->id}.channels", ['in_app'])
        ->set("preferences.{$this->type->id}.is_muted", false)
        ->call('save')
        ->assertHasNoErrors();

    $preference = NotificationPreference::query()
        ->where('user_id', $this->user->id)
        ->where('notification_type_id', $this->type->id)
        ->first();

    expect($preference)->not->toBeNull()
        ->and($preference->channels)->toBe(['in_app']);
});

test('member cannot enable channels tenant has disabled', function () {
    $this->actingAs($this->user);
    NotificationSetting::factory()->create([
        'notification_type_id' => $this->type->id,
        'channels' => ['in_app'],
        'is_enabled' => true,
    ]);

    Volt::test('settings.notifications')
        ->set("preferences.{$this->type->id}.channels", ['in_app', 'email'])
        ->set("preferences.{$this->type->id}.is_muted", false)
        ->call('save')
        ->assertHasErrors();
});

test('save member preferences rejects cross-user writes', function () {
    $action = app(SaveMemberNotificationPreferences::class);

    expect(fn () => $action($this->user, new SaveMemberNotificationPreferencesData(
        targetUserId: $this->otherUser->id,
        notificationTypeId: $this->type->id,
        channels: ['in_app'],
    )))->toThrow(AuthorizationException::class);
});

test('disabled tenant notification types are hidden from member preferences', function () {
    $this->actingAs($this->user);
    NotificationSetting::factory()->create([
        'notification_type_id' => $this->type->id,
        'channels' => ['in_app'],
        'is_enabled' => false,
    ]);

    Volt::test('settings.notifications')
        ->assertDontSee($this->type->name);
});
