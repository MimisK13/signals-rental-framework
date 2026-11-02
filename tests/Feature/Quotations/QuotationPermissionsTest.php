<?php

declare(strict_types=1);

use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Policies\QuotationPolicy;
use App\Models\User;
use App\Services\Api\AbilityRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('registers quotation permissions, abilities, and policy', function (): void {
    $permissions = PermissionSeeder::permissions();
    $abilities = app(AbilityRegistry::class);

    expect(array_keys($permissions))->toContain(
        'quotations.access',
        'quotations.view',
        'quotations.create',
        'quotations.edit',
        'quotations.delete',
        'quotations.send',
        'quotations.accept',
    )->and($abilities->has('quotations:read'))->toBeTrue()
        ->and($abilities->has('quotations:write'))->toBeTrue()
        ->and(Gate::getPolicyFor(Quotation::class))->toBeInstanceOf(QuotationPolicy::class);
});

it('grants quotation workflow permissions to Sales while preserving read-only access', function (): void {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');
    $readOnly = User::factory()->create();
    $readOnly->assignRole('Read Only');

    expect($sales->can('quotations.create'))->toBeTrue()
        ->and($sales->can('quotations.send'))->toBeTrue()
        ->and($sales->can('quotations.accept'))->toBeTrue()
        ->and($readOnly->can('quotations.view'))->toBeTrue()
        ->and($readOnly->can('quotations.create'))->toBeFalse();
});

arch('Quotations domain is independent from legacy Opportunity classes', function (): void {
    expect('App\Domain\Quotations')->not->toUse([
        'App\Models\Opportunity',
        'App\Models\OpportunityVersion',
        'App\Models\OpportunityItem',
        'App\Actions\Opportunities',
        'App\Verbs\Events\Opportunities',
        'App\Verbs\States\OpportunityState',
    ]);
});
