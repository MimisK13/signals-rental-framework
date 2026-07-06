<?php

use App\Enums\MeteringChannel;
use App\Models\InboundWebhookSource;
use App\Models\UsageDaily;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    Carbon::setTestNow('2026-07-01 12:00:00');
    $this->seed(PermissionSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders the api index for a user with api.access', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('api.access');

    $this->actingAs($user)
        ->get(route('api.index'))
        ->assertOk()
        ->assertSee('API Console')
        ->assertSee('Monitor API activity, explore endpoints, and manage webhooks.');
});

it('forbids the api index without api.access', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('api.index'))
        ->assertForbidden();
});

it('shows quick stat values from seeded data', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('api.access');

    $user->createToken('dashboard-token', ['products:read']);
    $user->createToken('backup-token', ['members:read']);

    UsageDaily::factory()->create([
        'date' => '2026-06-29',
        'channel' => MeteringChannel::Api->value,
        'request_count' => 120,
        'weighted_units' => 45,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'channel' => MeteringChannel::WebhookSent->value,
        'request_count' => 30,
        'weighted_units' => 15,
    ]);

    Webhook::factory()->create(['is_active' => true]);
    Webhook::factory()->create(['is_active' => false]);
    InboundWebhookSource::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('api.index'))
        ->assertOk()
        ->assertSee('150', false)
        ->assertSee('60', false)
        ->assertSee('2', false)
        ->assertSee('1', false)
        ->assertSee('3 inbound sources', false);
});
