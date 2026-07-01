<?php

use App\Services\Api\AbilityRegistry;
use App\Services\Api\CoreAbilityDefinitions;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Legacy ability keys previously hardcoded in admin/settings/api.blade.php.
 *
 * @return array<string, string>
 */
function legacyApiTokenAbilities(): array
{
    return [
        'members:read' => 'Read members',
        'members:write' => 'Write members',
        'countries:read' => 'Read countries',
        'currencies:read' => 'Read currencies',
        'exchange_rates:read' => 'Read exchange rates',
        'exchange_rates:write' => 'Write exchange rates',
        'products:read' => 'Read products & product groups',
        'products:write' => 'Write products & product groups',
        'stock:read' => 'Read stock levels & transactions',
        'stock:write' => 'Write stock levels & transactions',
        'availability:read' => 'Read product availability',
        'containers:read' => 'Read containers & contents',
        'containers:write' => 'Pack & unpack containers',
        'kits:read' => 'Read kit compositions',
        'kits:write' => 'Manage kit compositions',
        'activities:read' => 'Read activities',
        'activities:write' => 'Write activities',
        'opportunities:read' => 'Read opportunities',
        'opportunities:write' => 'Write opportunities',
        'shortages:read' => 'Read shortages & resolutions',
        'shortages:write' => 'Write shortage resolutions',
        'rates:read' => 'Read rate definitions & product rates',
        'rates:write' => 'Write rate definitions & product rates',
        'schema:read' => 'Read schema / field metadata',
        'custom-fields:read' => 'Read custom fields',
        'custom-fields:write' => 'Write custom fields',
        'static-data:read' => 'Read static data (lists)',
        'static-data:write' => 'Write static data (lists)',
        'tax-classes:read' => 'Read tax classes',
        'tax-classes:write' => 'Write tax classes',
        'settings:read' => 'Read settings',
        'settings:write' => 'Write settings',
        'users:read' => 'Read users',
        'users:write' => 'Write users',
        'roles:read' => 'Read roles',
        'roles:write' => 'Write roles',
        'webhooks:manage' => 'Manage webhooks',
        'system:read' => 'Read system info',
        'action-log:read' => 'Read action logs',
    ];
}

it('registers every legacy api token ability from the admin picker', function () {
    $registry = app(AbilityRegistry::class);

    expect($registry->all())->toHaveCount(count(legacyApiTokenAbilities()))
        ->and($registry->labels())->toEqual(legacyApiTokenAbilities())
        ->and($registry->has('products:read'))->toBeTrue()
        ->and($registry->get('products:read'))->toMatchArray([
            'key' => 'products:read',
            'label' => 'Read products & product groups',
            'group' => 'products',
            'description' => 'Read products & product groups',
        ]);
});

it('groups abilities by their resource prefix', function () {
    $registry = app(AbilityRegistry::class);

    expect($registry->groups())
        ->toHaveKey('products')
        ->and($registry->groups()['products']['abilities'])
        ->toHaveKeys(['products:read', 'products:write']);
});

it('matches the core ability definition source', function () {
    $registry = app(AbilityRegistry::class);

    expect(array_keys($registry->all()))->toEqual(array_keys(CoreAbilityDefinitions::all()));
});
