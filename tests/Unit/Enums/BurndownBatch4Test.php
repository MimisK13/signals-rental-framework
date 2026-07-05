<?php

/**
 * P4-CL4b burndown batch 4 — enum coverage.
 */

use App\Enums\ContainerDissolveReason;
use Tests\TestCase;

// ContainerDissolveReason::configValues() reads config(), which needs the app.
uses(TestCase::class);

it('ContainerDissolveReason label and configValues', function () {
    expect(ContainerDissolveReason::Dispatched->label())->toBe('Dispatched')
        ->and(ContainerDissolveReason::Manual->label())->toBe('Manual')
        ->and(ContainerDissolveReason::configValues())->toBeArray();
});

it('ContainerDissolveReason configValues reads configured dissolve reasons', function () {
    config(['containers.dissolve_reasons' => ['dispatched', 'manual']]);

    expect(ContainerDissolveReason::configValues())->toBe(['dispatched', 'manual']);
});
