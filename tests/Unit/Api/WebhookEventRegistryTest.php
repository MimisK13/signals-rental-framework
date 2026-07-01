<?php

use App\Services\Api\WebhookEventRegistry;
use App\Services\Api\WebhookService;
use Tests\TestCase;

uses(TestCase::class);

it('exposes names parity with the legacy WebhookService allow-list', function () {
    $registry = app(WebhookEventRegistry::class);

    expect($registry->names())->toEqual(WebhookService::EVENTS)
        ->and($registry->names())->toHaveCount(count(WebhookEventRegistry::CORE_EVENT_NAMES));
});

it('defaults webhook event weights to 1', function () {
    $registry = app(WebhookEventRegistry::class);

    foreach ($registry->all() as $event) {
        expect($event['weight'])->toBe(1);
    }

    expect($registry->weight('product.created'))->toBe(1)
        ->and($registry->weight('opportunity.converted_to_order'))->toBe(1);
});

it('groups webhook events by their resource prefix', function () {
    $registry = app(WebhookEventRegistry::class);

    expect($registry->groups())
        ->toHaveKey('product')
        ->and($registry->groups()['product']['events'])
        ->toContain('product.created');
});
