<?php

use App\Models\Invoice;
use App\Models\User;
use App\Policies\InvoicePolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('InvoicePolicy', function () {
    it('grants every ability to an owner', function () {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();

        expect($owner->can('viewAny', Invoice::class))->toBeTrue()
            ->and($owner->can('view', $invoice))->toBeTrue()
            ->and($owner->can('create', Invoice::class))->toBeTrue()
            ->and($owner->can('update', $invoice))->toBeTrue()
            ->and($owner->can('delete', $invoice))->toBeTrue();
    });

    it('maps each ability to the correct invoices.* permission', function () {
        $invoice = Invoice::factory()->create();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('invoices.view');
        $creator = User::factory()->create();
        $creator->givePermissionTo('invoices.create');
        $editor = User::factory()->create();
        $editor->givePermissionTo('invoices.edit');
        $deleter = User::factory()->create();
        $deleter->givePermissionTo('invoices.delete');

        expect($viewer->can('view', $invoice))->toBeTrue()
            ->and($creator->can('create', Invoice::class))->toBeTrue()
            ->and($editor->can('update', $invoice))->toBeTrue()
            ->and($deleter->can('delete', $invoice))->toBeTrue();
    });

    it('denies a user without the corresponding permission', function () {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create();

        expect($user->can('view', $invoice))->toBeFalse()
            ->and($user->can('create', Invoice::class))->toBeFalse()
            ->and($user->can('update', $invoice))->toBeFalse()
            ->and($user->can('delete', $invoice))->toBeFalse();
    });

    it('uses invoices.create as the manage permission', function () {
        $policy = new InvoicePolicy;
        $method = new ReflectionMethod($policy, 'managePermission');

        expect($method->invoke($policy))->toBe('invoices.create');
    });
});
