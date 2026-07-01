<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['api.access', 'api.explorer.use']);
});

it('renders the explorer page for a user with api.explorer.use', function () {
    $this->actingAs($this->user)
        ->get(route('api.explorer'))
        ->assertOk()
        ->assertSee('API Explorer')
        ->assertSee('Interactive Request Builder');
});

it('forbids the explorer page without api.explorer.use', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('api.explorer'))
        ->assertForbidden();
});

it('populates selectors from the endpoint catalogue', function () {
    $this->actingAs($this->user);

    Volt::test('api.explorer')
        ->assertOk()
        ->assertSee('Products')
        ->assertSee('GET');
});

it('updates the resolved url when the operation changes', function () {
    $this->actingAs($this->user);

    Volt::test('api.explorer')
        ->set('selectedResource', 'products')
        ->set('selectedRouteName', 'api.v1.products.index')
        ->assertSet('resolvedUrl', '/api/v1/products');
});

it('shows path parameter inputs and substitutes them into the resolved url', function () {
    $this->actingAs($this->user);

    Volt::test('api.explorer')
        ->set('selectedResource', 'activities')
        ->set('selectedRouteName', 'api.v1.activities.show')
        ->assertSet('hasPathParameters', true)
        ->set('pathParams.activity', '42')
        ->assertSet('resolvedUrl', '/api/v1/activities/42');
});

it('requires path parameter values before making a request', function () {
    Http::fake();

    $token = $this->user->createToken('explorer-token', ['activities:read']);
    $this->actingAs($this->user);

    Volt::test('api.explorer')
        ->set('selectedResource', 'activities')
        ->set('selectedRouteName', 'api.v1.activities.show')
        ->set('selectedTokenId', $token->accessToken->id)
        ->call('makeRequest')
        ->assertHasErrors(['pathParams.activity']);
});

it('sends requests with substituted path parameters', function () {
    Http::fake(function ($request) {
        expect($request->url())->toBe('https://signals.test/api/v1/activities/42');

        return Http::response(['activity' => ['id' => 42]], 200, ['Content-Type' => 'application/json']);
    });

    config(['app.url' => 'https://signals.test']);

    $token = $this->user->createToken('explorer-token', ['activities:read']);
    $this->actingAs($this->user);

    Volt::test('api.explorer')
        ->set('selectedResource', 'activities')
        ->set('selectedRouteName', 'api.v1.activities.show')
        ->set('pathParams.activity', '42')
        ->set('selectedTokenId', $token->accessToken->id)
        ->call('makeRequest')
        ->assertSet('response.status', 200)
        ->assertSee('s-json-key', false);
});

it('populates the response panel after making a request', function () {
    Http::fake([
        '*' => Http::response(['products' => []], 200, ['Content-Type' => 'application/json']),
    ]);

    $token = $this->user->createToken('catalogue-token', ['products:read']);

    $this->actingAs($this->user);

    Volt::test('api.explorer')
        ->set('selectedResource', 'products')
        ->set('selectedRouteName', 'api.v1.products.index')
        ->set('selectedTokenId', $token->accessToken->id)
        ->call('makeRequest')
        ->assertSet('response.status', 200)
        ->assertSet('response.status_class', '2xx')
        ->assertSet('response.body', '{"products":[]}');
});

it('uses a pasted token without minting an ephemeral token', function () {
    Http::fake(function ($request) {
        expect($request->header('Authorization'))->toBe(['Bearer pasted-token-value']);

        return Http::response('plain-text-body', 200);
    });

    $token = $this->user->createToken('existing-token', ['products:read']);
    $initialCount = $this->user->tokens()->count();

    $this->actingAs($this->user);

    Volt::test('api.explorer')
        ->set('selectedResource', 'products')
        ->set('selectedRouteName', 'api.v1.products.index')
        ->set('selectedTokenId', $token->accessToken->id)
        ->set('rawToken', 'pasted-token-value')
        ->call('makeRequest')
        ->assertSet('response.body', 'plain-text-body');

    expect($this->user->fresh()->tokens()->count())->toBe($initialCount);
});

it('mints and deletes an ephemeral token when emulating selected abilities', function () {
    Http::fake([
        '*' => Http::response(['ok' => true], 200, ['Content-Type' => 'application/json']),
    ]);

    $token = $this->user->createToken('source-token', ['products:read']);
    $initialCount = $this->user->tokens()->count();

    $this->actingAs($this->user);

    Volt::test('api.explorer')
        ->set('selectedResource', 'products')
        ->set('selectedRouteName', 'api.v1.products.index')
        ->set('selectedTokenId', $token->accessToken->id)
        ->call('makeRequest')
        ->assertSet('response.status', 200);

    expect($this->user->fresh()->tokens()->count())->toBe($initialCount);
    expect($this->user->fresh()->tokens()->pluck('name'))->not->toContain('api-explorer');
});
