<?php

use App\Services\Api\AbilityRegistry;
use App\Services\Api\CoreAbilityDefinitions;

it('registers invoice and payment sanctum abilities', function () {
    $registry = app(AbilityRegistry::class);

    expect($registry->has('invoices:read'))->toBeTrue()
        ->and($registry->has('invoices:write'))->toBeTrue()
        ->and($registry->has('payments:read'))->toBeTrue()
        ->and($registry->has('payments:write'))->toBeTrue()
        ->and($registry->get('invoices:read'))->toMatchArray([
            'key' => 'invoices:read',
            'label' => 'Read invoices',
            'group' => 'invoices',
            'description' => 'Read invoices and payments',
        ]);
});

it('includes invoice abilities in core definitions', function () {
    expect(array_keys(CoreAbilityDefinitions::all()))->toContain(
        'invoices:read',
        'invoices:write',
        'payments:read',
        'payments:write',
    );
});
