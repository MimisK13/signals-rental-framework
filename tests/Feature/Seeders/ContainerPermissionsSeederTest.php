<?php

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('seeds the full container permission set', function () {
    $expected = [
        'containers.view',
        'containers.create',
        'containers.create_temporary',
        'containers.pack',
        'containers.pack_override',
        'containers.transfer',
        'containers.nest',
        'containers.seal',
        'containers.dissolve',
        'containers.manage_templates',
        'opportunities.dispatch_add_items',
    ];

    foreach ($expected as $permission) {
        expect(Permission::findByName($permission, 'web'))->not->toBeNull();
    }
});

it('assigns container permissions to warehouse and ops manager roles', function () {
    $warehouse = Role::findByName('Warehouse', 'web');
    $opsManager = Role::findByName('Operations Manager', 'web');

    expect($warehouse->hasPermissionTo('containers.pack'))->toBeTrue()
        ->and($warehouse->hasPermissionTo('containers.create'))->toBeTrue()
        ->and($warehouse->hasPermissionTo('containers.manage_templates'))->toBeTrue()
        ->and($opsManager->hasPermissionTo('containers.transfer'))->toBeTrue()
        ->and($opsManager->hasPermissionTo('opportunities.dispatch_add_items'))->toBeTrue();
});

it('limits read only role to view permissions', function () {
    $readOnly = Role::findByName('Read Only', 'web');

    expect($readOnly->hasPermissionTo('containers.view'))->toBeTrue()
        ->and($readOnly->hasPermissionTo('containers.pack'))->toBeFalse()
        ->and($readOnly->hasPermissionTo('containers.manage_templates'))->toBeFalse();
});
