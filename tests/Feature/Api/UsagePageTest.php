<?php

use App\Enums\MeteringChannel;
use App\Models\UsageDaily;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    Carbon::setTestNow('2026-07-01 12:00:00');
    $this->seed(PermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['api.access', 'api.usage.view']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders the usage page for a user with api.usage.view', function () {
    $this->actingAs($this->user)
        ->get(route('api.usage'))
        ->assertOk()
        ->assertSee('API Usage')
        ->assertSee('Aggregated request volume and weighted units');
});

it('forbids the usage page without api.usage.view', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('api.usage'))
        ->assertForbidden();
});

it('shows summary totals and breakdown tables for seeded usage data', function () {
    $tokenAlpha = $this->user->createToken('alpha-token', ['products:read']);
    $tokenBeta = $this->user->createToken('beta-token', ['members:read']);

    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::Api->value,
        'personal_access_token_id' => $tokenAlpha->accessToken->id,
        'key' => 'api.v1.products.index',
        'resource' => 'products',
        'status_class' => '2xx',
        'request_count' => 10,
        'weighted_units' => 20,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'channel' => MeteringChannel::Api->value,
        'personal_access_token_id' => $tokenBeta->accessToken->id,
        'key' => 'api.v1.members.index',
        'resource' => 'members',
        'status_class' => '4xx',
        'request_count' => 5,
        'weighted_units' => 8,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::WebhookSent->value,
        'key' => 'invoice.issued',
        'resource' => 'invoices',
        'status_class' => '2xx',
        'request_count' => 100,
        'weighted_units' => 100,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-06-15',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.opportunities.index',
        'resource' => 'opportunities',
        'status_class' => '5xx',
        'request_count' => 50,
        'weighted_units' => 50,
    ]);

    $this->actingAs($this->user);

    Volt::test('api.usage')
        ->assertSee('115')
        ->assertSee('128')
        ->assertSee('alpha-token')
        ->assertSee('beta-token')
        ->assertSee('api.v1.products.index')
        ->assertSee('api.v1.members.index')
        ->assertSee('products')
        ->assertSee('members')
        ->assertSee('invoice.issued')
        ->assertSee('invoices');
});

it('changes totals when the date window preset changes', function () {
    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.products.index',
        'resource' => 'products',
        'status_class' => '2xx',
        'request_count' => 111,
        'weighted_units' => 111,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-06-15',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.members.index',
        'resource' => 'members',
        'status_class' => '2xx',
        'request_count' => 999,
        'weighted_units' => 999,
    ]);

    $this->actingAs($this->user);

    Volt::test('api.usage')
        ->assertSee('111')
        ->assertDontSee('api.v1.members.index')
        ->call('setDatePreset', '30d')
        ->assertSee('1,110')
        ->assertSee('api.v1.members.index');
});

it('narrows totals when a channel filter is applied', function () {
    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.products.index',
        'resource' => 'products',
        'status_class' => '2xx',
        'request_count' => 42,
        'weighted_units' => 42,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::WebhookSent->value,
        'key' => 'invoice.issued',
        'resource' => 'invoices',
        'status_class' => '2xx',
        'request_count' => 9876,
        'weighted_units' => 9876,
    ]);

    $this->actingAs($this->user);

    Volt::test('api.usage')
        ->assertSee('9,918')
        ->assertSee('invoice.issued')
        ->call('setChannel', 'api')
        ->assertSee('42')
        ->assertDontSee('invoice.issued')
        ->assertDontSee('9,876');
});

it('renders status split counts for 2xx 4xx and 5xx', function () {
    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.ok',
        'resource' => 'products',
        'status_class' => '2xx',
        'request_count' => 12,
        'weighted_units' => 12,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.bad-request',
        'resource' => 'products',
        'status_class' => '4xx',
        'request_count' => 3,
        'weighted_units' => 3,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.error',
        'resource' => 'products',
        'status_class' => '5xx',
        'request_count' => 2,
        'weighted_units' => 2,
    ]);

    $this->actingAs($this->user);

    Volt::test('api.usage')
        ->assertSee('Status split')
        ->assertSee('2xx')
        ->assertSee('4xx')
        ->assertSee('5xx')
        ->assertSee('12')
        ->assertSee('3')
        ->assertSee('2');
});

it('streams a CSV download synchronously and toasts when exporting', function () {
    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.products.index',
        'resource' => 'products',
        'status_class' => '2xx',
        'request_count' => 7,
        'weighted_units' => 7,
    ]);

    $this->actingAs($this->user);

    Volt::test('api.usage')
        ->call('setChannel', 'api')
        ->set('filterDatePreset', '24h')
        ->call('exportCsv')
        ->assertFileDownloaded()
        ->assertDispatched('toast');
});
