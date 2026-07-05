<?php

use App\Contracts\HasSchema;
use App\Models\InboundWebhookSource;
use App\Models\RequestLog;
use App\Services\SchemaBuilder;
use App\Services\SchemaRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('implements HasSchema on request logs', function () {
    expect(new RequestLog)->toBeInstanceOf(HasSchema::class);
});

it('implements HasSchema on inbound webhook sources', function () {
    expect(new InboundWebhookSource)->toBeInstanceOf(HasSchema::class);
});

it('resolves a non-empty schema for request logs', function () {
    $schema = (new SchemaRegistry)->resolve(RequestLog::class);

    expect($schema)->not->toBeEmpty();
});

it('resolves a non-empty schema for inbound webhook sources', function () {
    $schema = (new SchemaRegistry)->resolve(InboundWebhookSource::class);

    expect($schema)->not->toBeEmpty();
});

it('registers filterable request log fields in the schema', function () {
    $schema = (new SchemaRegistry)->resolve(RequestLog::class);

    expect($schema)->toHaveKeys(['method', 'route_name', 'status_code', 'created_at'])
        ->and($schema['method']->filterable)->toBeTrue()
        ->and($schema['status_code']->filterable)->toBeTrue()
        ->and($schema['status_code']->sortable)->toBeTrue();
});

it('registers inbound webhook source fields in the schema', function () {
    $schema = (new SchemaRegistry)->resolve(InboundWebhookSource::class);

    expect($schema)->toHaveKeys(['name', 'slug', 'is_active', 'price_weight'])
        ->and($schema['name']->filterable)->toBeTrue()
        ->and($schema['is_active']->filterable)->toBeTrue()
        ->and($schema)->not->toHaveKey('secret');
});

it('defines request log schema fields directly', function () {
    $builder = new SchemaBuilder;
    RequestLog::defineSchema($builder);
    $definitions = $builder->build();

    expect($definitions)->toHaveKeys(['method', 'route_name', 'status_code']);
});

it('defines inbound webhook source schema fields directly', function () {
    $builder = new SchemaBuilder;
    InboundWebhookSource::defineSchema($builder);
    $definitions = $builder->build();

    expect($definitions)->toHaveKeys(['name', 'slug', 'is_active'])
        ->and($definitions)->not->toHaveKey('secret');
});
