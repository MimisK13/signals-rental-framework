<?php

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\ProductSupplierCost;
use App\Models\Store;
use App\Models\User;
use App\Policies\BundleRulePolicy;
use App\Policies\CreditNotePolicy;
use App\Policies\DiscountRulePolicy;
use App\Policies\ProductSupplierCostPolicy;
use App\Policies\SurchargeGroupPolicy;
use App\Policies\SurchargePolicy;
use App\Policies\TaxCategoryPolicy;
use App\Policies\TaxZonePolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('exposes pricing view permissions on pricing policies', function (string $policyClass) {
    $policy = new $policyClass;
    $method = new ReflectionMethod($policy, 'viewPermission');
    $method->setAccessible(true);

    expect($method->invoke($policy))->toBe('pricing.view');
})->with([
    BundleRulePolicy::class,
    DiscountRulePolicy::class,
    SurchargeGroupPolicy::class,
    SurchargePolicy::class,
]);

it('exposes tax-classes view permission on tax policies', function (string $policyClass) {
    $policy = new $policyClass;
    $method = new ReflectionMethod($policy, 'viewPermission');
    $method->setAccessible(true);

    expect($method->invoke($policy))->toBe('tax-classes.view');
})->with([
    TaxCategoryPolicy::class,
    TaxZonePolicy::class,
]);

it('allows ProductSupplierCostPolicy view when list access is granted', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['costs.view', 'products.view']);

    $policy = new ProductSupplierCostPolicy;

    expect($policy->view($user, ProductSupplierCost::factory()->create()))->toBeTrue();
});

it('denies CreditNotePolicy view without invoices.view permission', function () {
    $creditNote = CreditNote::factory()->create();

    expect((new CreditNotePolicy)->view(User::factory()->create(), $creditNote))->toBeFalse()
        ->and((new CreditNotePolicy)->view(User::factory()->create()))->toBeFalse();
});

it('denies CreditNotePolicy view without store access even when invoices.view is granted', function () {
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $invoice = Invoice::factory()->create(['store_id' => $store->id]);
    $creditNote = CreditNote::factory()->create([
        'invoice_id' => $invoice->id,
        'store_id' => $store->id,
    ]);

    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id]);
    $user->givePermissionTo('invoices.view');
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $otherStore->id,
        'created_at' => now(),
    ]);

    $policy = new CreditNotePolicy;

    expect($policy->view($user, $creditNote))->toBeFalse()
        ->and($policy->view($user))->toBeTrue();
});

it('allows CreditNotePolicy view when the user can access the credit note store', function () {
    $store = Store::factory()->create();
    $invoice = Invoice::factory()->create(['store_id' => $store->id]);
    $creditNote = CreditNote::factory()->create([
        'invoice_id' => $invoice->id,
        'store_id' => $store->id,
    ]);

    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id]);
    $user->givePermissionTo('invoices.view');
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'created_at' => now(),
    ]);

    expect((new CreditNotePolicy)->view($user, $creditNote))->toBeTrue();
});
