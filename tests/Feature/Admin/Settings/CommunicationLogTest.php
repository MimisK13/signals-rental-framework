<?php

use App\Models\CommunicationLog;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->manager = User::factory()->admin()->create();
    $this->manager->givePermissionTo('notifications.manage');

    $this->viewer = User::factory()->create(['is_owner' => false]);
});

test('communication log admin page requires notifications.manage', function () {
    $this->actingAs($this->viewer)
        ->get(route('admin.settings.communication-log'))
        ->assertForbidden();
});

test('communication log admin page lists entries for authorized users', function () {
    $log = CommunicationLog::factory()->sent()->create([
        'notification_type_key' => 'opportunity.confirmed',
        'channel' => 'email',
    ]);

    $this->actingAs($this->manager)
        ->get(route('admin.settings.communication-log'))
        ->assertOk()
        ->assertSee('Communication Log')
        ->assertSee('opportunity.confirmed')
        ->assertSee('email');
});

test('communication log filters by status', function () {
    $this->actingAs($this->manager);

    CommunicationLog::factory()->sent()->create([
        'status' => 'sent',
        'notification_type_key' => 'filter.sent.only',
    ]);
    CommunicationLog::factory()->failed()->create([
        'status' => 'failed',
        'notification_type_key' => 'filter.failed.only',
    ]);

    Volt::test('admin.settings.communication-log')
        ->set('filterStatus', 'failed')
        ->assertSee('filter.failed.only')
        ->assertDontSee('filter.sent.only');
});

test('communication log pin toggle persists', function () {
    $this->actingAs($this->manager);

    $log = CommunicationLog::factory()->create(['is_pinned' => false]);

    Volt::test('admin.settings.communication-log')
        ->call('togglePin', $log->id);

    expect($log->fresh()->is_pinned)->toBeTrue();
});

test('communication log respects store scoping for opportunity subjects', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    $restricted = User::factory()->create(['is_owner' => false, 'is_admin' => false]);
    MemberStore::create(['member_id' => $restricted->member_id, 'store_id' => $storeA->id, 'created_at' => now()]);
    $restricted->givePermissionTo('notifications.manage');
    $this->actingAs($restricted);

    $oppA = Opportunity::factory()->create(['store_id' => $storeA->id]);
    $oppB = Opportunity::factory()->create(['store_id' => $storeB->id]);

    CommunicationLog::factory()->create([
        'subject_type' => Opportunity::class,
        'subject_id' => $oppA->id,
        'notification_type_key' => 'visible.log',
    ]);
    CommunicationLog::factory()->create([
        'subject_type' => Opportunity::class,
        'subject_id' => $oppB->id,
        'notification_type_key' => 'hidden.log',
    ]);

    Volt::test('admin.settings.communication-log')
        ->assertSee('visible.log')
        ->assertDontSee('hidden.log');
});

test('opportunity communications tab lists subject-scoped log rows', function () {
    $user = User::factory()->admin()->create();
    $user->givePermissionTo('opportunities.view');

    $opportunity = Opportunity::factory()->create();
    $other = Opportunity::factory()->create();

    CommunicationLog::factory()->create([
        'subject_type' => Opportunity::class,
        'subject_id' => $opportunity->id,
        'notification_type_key' => 'opp.tab.match',
    ]);
    CommunicationLog::factory()->create([
        'subject_type' => Opportunity::class,
        'subject_id' => $other->id,
        'notification_type_key' => 'opp.tab.other',
    ]);

    $this->actingAs($user)
        ->get(route('opportunities.communications', $opportunity))
        ->assertOk()
        ->assertSee('opp.tab.match')
        ->assertDontSee('opp.tab.other');
});

test('member communications tab lists subject-scoped log rows', function () {
    $user = User::factory()->admin()->create();
    $user->givePermissionTo('members.access');

    $member = Member::factory()->create();
    $other = Member::factory()->create();

    CommunicationLog::factory()->create([
        'subject_type' => Member::class,
        'subject_id' => $member->id,
        'notification_type_key' => 'member.tab.match',
    ]);
    CommunicationLog::factory()->create([
        'subject_type' => Member::class,
        'subject_id' => $other->id,
        'notification_type_key' => 'member.tab.other',
    ]);

    $this->actingAs($user)
        ->get(route('members.communications', $member))
        ->assertOk()
        ->assertSee('member.tab.match')
        ->assertDontSee('member.tab.other');
});
