<?php

use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\TestPipelineNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->member = $this->user->member;
});

test('notification pane lists unread notifications for the authenticated member', function () {
    $this->actingAs($this->user);
    $opportunity = Opportunity::factory()->create();

    $this->member->notify(new TestPipelineNotification(
        typeKey: 'test.alert',
        laravelChannels: ['database'],
        payload: [
            'title' => 'Opportunity updated',
            'body' => 'Something changed.',
            'opportunity' => ['id' => $opportunity->id],
        ],
    ));

    Volt::test('notifications.pane')
        ->assertSee('Opportunity updated')
        ->assertSee('Something changed.')
        ->assertSet('unreadCount', 1);
});

test('notification bell shows unread badge count', function () {
    $this->actingAs($this->user);
    $this->member->notify(new TestPipelineNotification(
        typeKey: 'test.alert',
        laravelChannels: ['database'],
        payload: ['title' => 'Ping', 'body' => 'Hello'],
    ));

    Volt::test('notifications.bell')
        ->assertSee('1');
});

test('notification pane refreshes when opened and stays consistent with bell', function () {
    $this->actingAs($this->user);

    $pane = Volt::test('notifications.pane')
        ->assertSet('unreadCount', 0);

    $this->member->notify(new TestPipelineNotification(
        typeKey: 'test.alert',
        laravelChannels: ['database'],
        payload: ['title' => 'Live ping', 'body' => 'From polling'],
    ));

    $pane->set('items', [])->set('unreadCount', 0);

    Volt::test('notifications.bell')
        ->call('refreshCount')
        ->assertSet('unreadCount', 1);

    $pane->call('refreshNotifications')
        ->assertSet('unreadCount', 1)
        ->assertSee('Live ping')
        ->assertSee('From polling');
});

test('notification pane refreshes when bell polling detects a new unread count', function () {
    $this->actingAs($this->user);

    $bell = Volt::test('notifications.bell')
        ->assertSet('unreadCount', 0);

    $pane = Volt::test('notifications.pane')
        ->assertSet('unreadCount', 0);

    $this->member->notify(new TestPipelineNotification(
        typeKey: 'test.alert',
        laravelChannels: ['database'],
        payload: ['title' => 'Stale sync', 'body' => 'Updated from bell poll'],
    ));

    $pane->set('items', [])->set('unreadCount', 0);

    $bell->call('refreshCount')
        ->assertSet('unreadCount', 1)
        ->assertDispatched('notifications-stale');

    $pane->call('onNotificationsStale')
        ->assertSet('unreadCount', 1)
        ->assertSee('Stale sync');
});

test('mark read decrements unread count', function () {
    $this->actingAs($this->user);
    $this->member->notify(new TestPipelineNotification(
        typeKey: 'test.alert',
        laravelChannels: ['database'],
        payload: ['title' => 'Ping', 'body' => 'Hello'],
    ));

    $notificationId = $this->member->fresh()->notifications->first()->id;

    Volt::test('notifications.pane')
        ->call('markRead', $notificationId)
        ->assertSet('unreadCount', 0);
});

test('mark all read clears unread count', function () {
    $this->actingAs($this->user);
    $this->member->notify(new TestPipelineNotification(
        typeKey: 'test.alert',
        laravelChannels: ['database'],
        payload: ['title' => 'One', 'body' => 'A'],
    ));
    $this->member->notify(new TestPipelineNotification(
        typeKey: 'test.alert',
        laravelChannels: ['database'],
        payload: ['title' => 'Two', 'body' => 'B'],
    ));

    Volt::test('notifications.pane')
        ->call('markAllRead')
        ->assertSet('unreadCount', 0);
});

test('notification pane prepends on broadcast listener refresh', function () {
    $this->actingAs($this->user);

    Volt::test('notifications.pane')
        ->call('onBroadcastNotificationCreated')
        ->assertSet('unreadCount', 0);

    $this->member->notify(new TestPipelineNotification(
        typeKey: 'test.alert',
        laravelChannels: ['database'],
        payload: ['title' => 'Live ping', 'body' => 'From Echo'],
    ));

    Volt::test('notifications.pane')
        ->call('onBroadcastNotificationCreated')
        ->assertSee('Live ping')
        ->assertSet('unreadCount', 1);
});

test('notification link resolves opportunity show route from payload', function () {
    $this->actingAs($this->user);
    $opportunity = Opportunity::factory()->create();

    $this->member->notify(new TestPipelineNotification(
        typeKey: 'test.alert',
        laravelChannels: ['database'],
        payload: [
            'title' => 'Linked',
            'body' => 'Body',
            'opportunity' => ['id' => $opportunity->id],
        ],
    ));

    Volt::test('notifications.pane')
        ->assertSee(route('opportunities.show', $opportunity, absolute: false));
});

test('notification pane hides preferences footer markup class for collapsed styling', function () {
    $this->actingAs($this->user);

    Volt::test('notifications.pane')
        ->assertSee('app-notifications-mount', false)
        ->assertSee('app-notifications-footer', false)
        ->assertSee('Notification preferences');
});

test('notification pane mount fills the app layout height', function () {
    $this->actingAs($this->user);

    Volt::test('notifications.pane')
        ->assertSee('app-notifications-mount', false)
        ->assertSee('app-notifications-pane', false)
        ->assertSee('notifications-toggle', false);
});
