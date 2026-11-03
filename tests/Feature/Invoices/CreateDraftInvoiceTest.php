<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Invoices\Actions\CreateDraftInvoice;
use App\Domain\Invoices\Data\CreateDraftInvoiceData;
use App\Domain\Invoices\Enums\InvoiceType;
use App\Domain\Members\Models\Member;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Stores\Models\Store;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a draft invoice with config-driven default status', function () {
    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create();

    $result = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'type' => InvoiceType::Deposit->value,
        'notes' => 'Deposit invoice',
    ]));

    expect($result->status)->toBe('draft')
        ->and($result->status_label)->toBe('Draft')
        ->and($result->type)->toBe('deposit')
        ->and($result->notes)->toBe('Deposit invoice')
        ->and($result->total)->toBe('0.00');
});

it('links an invoice to an opportunity when supplied', function () {
    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create();
    $opportunity = Opportunity::factory()->create([
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]);

    $result = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'opportunity_id' => $opportunity->id,
    ]));

    expect($result->opportunity_id)->toBe($opportunity->id)
        ->and($opportunity->fresh()->invoices)->toHaveCount(1);
});
