<?php

use App\Models\InboundWebhookLog;
use App\Models\InboundWebhookSource;
use App\Models\RequestLog;
use App\Models\User;
use App\Models\WebhookLog;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['api.access', 'api.logs.view']);
});

it('renders the logs page for a user with api.logs.view', function () {
    $this->actingAs($this->user)
        ->get(route('api.logs'))
        ->assertOk()
        ->assertSee('Request Logs')
        ->assertSee('Channel-aware API & webhook log explorer');
});

it('forbids the logs page without api.logs.view', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('api.logs'))
        ->assertForbidden();
});

it('shows api request logs only on the api channel', function () {
    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.products.index',
        'method' => 'GET',
        'created_at' => now(),
    ]);

    WebhookLog::factory()->create([
        'event' => 'product.created',
        'created_at' => now(),
    ]);

    InboundWebhookLog::factory()->create([
        'event' => 'order.created',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user);

    Volt::test('api.logs')
        ->assertSee('api.v1.products.index')
        ->assertSee('GET')
        ->assertDontSee('product.created')
        ->assertDontSee('order.created');
});

it('shows webhook sent logs on the webhook_sent channel', function () {
    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.products.index',
        'created_at' => now(),
    ]);

    WebhookLog::factory()->create([
        'event' => 'invoice.issued',
        'created_at' => now(),
    ]);

    InboundWebhookLog::factory()->create([
        'event' => 'order.created',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user);

    Volt::test('api.logs')
        ->call('setChannel', 'webhook_sent')
        ->assertSee('invoice.issued')
        ->assertDontSee('api.v1.products.index')
        ->assertDontSee('order.created');
});

it('shows inbound webhook logs on the webhook_received channel', function () {
    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.members.index',
        'created_at' => now(),
    ]);

    WebhookLog::factory()->create([
        'event' => 'member.created',
        'created_at' => now(),
    ]);

    $source = InboundWebhookSource::factory()->create(['name' => 'Stripe Connect']);
    InboundWebhookLog::factory()->for($source, 'inboundWebhookSource')->create([
        'event' => 'payment_intent.succeeded',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user);

    Volt::test('api.logs')
        ->call('setChannel', 'webhook_received')
        ->assertSee('Stripe Connect')
        ->assertSee('payment_intent.succeeded')
        ->assertDontSee('api.v1.members.index')
        ->assertDontSee('member.created');
});

it('filters api logs by status class', function () {
    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.ok',
        'status_code' => 200,
        'created_at' => now(),
    ]);

    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.error',
        'status_code' => 500,
        'created_at' => now(),
    ]);

    $this->actingAs($this->user);

    Volt::test('api.logs')
        ->set('filterStatusClass', '5xx')
        ->assertSee('api.v1.error')
        ->assertDontSee('api.v1.ok');
});

it('filters api logs by token', function () {
    $tokenA = $this->user->createToken('alpha-token', ['products:read']);
    $tokenB = $this->user->createToken('beta-token', ['members:read']);

    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.alpha',
        'personal_access_token_id' => $tokenA->accessToken->id,
        'created_at' => now(),
    ]);

    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.beta',
        'personal_access_token_id' => $tokenB->accessToken->id,
        'created_at' => now(),
    ]);

    $this->actingAs($this->user);

    Volt::test('api.logs')
        ->set('filterToken', (string) $tokenA->accessToken->id)
        ->assertSee('api.v1.alpha')
        ->assertDontSee('api.v1.beta');
});

it('filters logs by date window preset', function () {
    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.recent',
        'created_at' => now()->subHours(2),
    ]);

    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.old',
        'created_at' => now()->subDays(20),
    ]);

    $this->actingAs($this->user);

    Volt::test('api.logs')
        ->call('setDatePreset', '24h')
        ->assertSee('api.v1.recent')
        ->assertDontSee('api.v1.old')
        ->call('setDatePreset', '30d')
        ->assertSee('api.v1.recent')
        ->assertSee('api.v1.old');
});

it('reveals row detail when toggled', function () {
    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.products.show',
        'uri' => '/api/v1/products/42',
        'ability' => 'products:read',
        'request_body' => '{"id":42}',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user);

    $logId = RequestLog::query()->value('id');

    Volt::test('api.logs')
        ->call('toggleRow', $logId)
        ->assertSet('expandedRow', $logId)
        ->assertSee('/api/v1/products/42')
        ->assertSee('products:read')
        ->assertSeeHtml('<span class="s-json-key">&quot;id&quot;</span>')
        ->assertSeeHtml('<span class="s-json-number">42</span>');
});

it('streams a CSV download synchronously and toasts when exporting', function () {
    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.products.index',
        'method' => 'GET',
        'status_code' => 200,
        'created_at' => now(),
    ]);

    $this->actingAs($this->user);

    Volt::test('api.logs')
        ->call('setChannel', 'api')
        ->set('filterDatePreset', '24h')
        ->call('exportCsv')
        ->assertFileDownloaded()
        ->assertDispatched('toast');
});
