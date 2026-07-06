<?php

use App\Actions\Invoices\CreateDraftInvoice;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Enums\InvoiceType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

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
