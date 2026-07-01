<?php

use App\Services\Api\ApiEndpointCatalogue;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

uses(TestCase::class);

it('builds the api v1 endpoint catalogue', function () {
    $catalogue = app(ApiEndpointCatalogue::class);

    $endpoints = $catalogue->all();

    expect($endpoints)->not->toBeEmpty()
        ->and(collect($endpoints)->pluck('route_name'))->toContain('api.v1.products.index');
});

it('includes weight and required ability metadata for a known route', function () {
    $endpoint = app(ApiEndpointCatalogue::class)->get('api.v1.products.index');

    expect($endpoint)->not->toBeNull()
        ->and($endpoint['route_name'])->toBe('api.v1.products.index')
        ->and($endpoint['resource'])->toBe('products')
        ->and($endpoint['operation'])->toBe('index')
        ->and($endpoint['required_ability'])->toBe('products:read')
        ->and($endpoint['price_weight'])->toBe(1)
        ->and($endpoint['method'])->toBe('GET')
        ->and($endpoint['uri'])->toContain('products');
});

it('invalidates cached catalogue data when routes are cleared', function () {
    $catalogue = app(ApiEndpointCatalogue::class);

    $catalogue->all();

    $memory = new ReflectionProperty(ApiEndpointCatalogue::class, 'memory');
    $memory->setAccessible(true);

    expect($memory->getValue($catalogue))->not->toBeNull();

    Artisan::call('route:clear');

    event(new CommandFinished(
        'route:clear',
        new ArrayInput([]),
        new BufferedOutput,
        0,
    ));

    expect($memory->getValue($catalogue))->toBeNull();
});
