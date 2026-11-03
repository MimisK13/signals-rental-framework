<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Administration\Presentation\Livewire\Users;
use App\Domain\Notifications\Notifications\UserInvitedNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('renders the users page', function () {
    $this->get(route('admin.settings.users'))
        ->assertOk()
        ->assertSee('Users');
});

it('lists all users', function () {
    $other = User::factory()->create(['name' => 'Jane Doe']);

    Livewire::test(Users::class)
        ->assertSee($this->owner->name)
        ->assertSee('Jane Doe');
});

it('renders role badges with the blue badge class', function () {
    $user = User::factory()->create(['name' => 'Roled User']);
    $user->assignRole('Read Only');

    Livewire::test(Users::class)
        ->assertSeeHtml('<span class="s-badge s-badge-blue">Read Only</span>');
});

it('shows user status badges', function () {
    User::factory()->deactivated()->create(['name' => 'Deactivated User']);
    User::factory()->invited()->create(['name' => 'Invited User']);

    $this->get(route('admin.settings.users'))
        ->assertSee('Deactivated')
        ->assertSee('Invited')
        ->assertSee('Active');
});

it('invites a new user', function () {
    Notification::fake();

    Livewire::test(Users::class)
        ->call('openInviteModal')
        ->set('inviteName', 'New User')
        ->set('inviteEmail', 'new@example.com')
        ->set('inviteRoles', ['Read Only'])
        ->call('invite')
        ->assertHasNoErrors()
        ->assertDispatched('user-invited');

    $user = User::where('email', 'new@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('New User');
    expect($user->invited_at)->not->toBeNull();
    expect($user->password)->toBeNull();
    expect($user->hasRole('Read Only'))->toBeTrue();

    Notification::assertSentTo($user, UserInvitedNotification::class);
});

it('validates invite fields', function () {
    Livewire::test(Users::class)
        ->call('openInviteModal')
        ->set('inviteName', '')
        ->set('inviteEmail', 'not-email')
        ->call('invite')
        ->assertHasErrors(['inviteName', 'inviteEmail']);
});

it('requires at least one role when inviting', function () {
    Livewire::test(Users::class)
        ->call('openInviteModal')
        ->set('inviteName', 'No Role User')
        ->set('inviteEmail', 'norole@example.com')
        ->set('inviteRoles', [])
        ->call('invite')
        ->assertHasErrors(['inviteRoles']);
});

it('prevents inviting duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    Livewire::test(Users::class)
        ->call('openInviteModal')
        ->set('inviteName', 'Duplicate')
        ->set('inviteEmail', 'existing@example.com')
        ->call('invite')
        ->assertHasErrors(['inviteEmail']);
});

it('deactivates a user', function () {
    $user = User::factory()->create();

    Livewire::test(Users::class)
        ->call('deactivate', $user->id)
        ->assertDispatched('user-deactivated');

    expect($user->fresh()->isActive())->toBeFalse();
    expect($user->fresh()->deactivated_at)->not->toBeNull();
});

it('reactivates a user', function () {
    $user = User::factory()->deactivated()->create();

    Livewire::test(Users::class)
        ->call('reactivate', $user->id)
        ->assertDispatched('user-reactivated');

    expect($user->fresh()->isActive())->toBeTrue();
    expect($user->fresh()->deactivated_at)->toBeNull();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.users'))
        ->assertForbidden();
});
