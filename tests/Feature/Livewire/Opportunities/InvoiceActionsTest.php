<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityState;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    settings()->set('invoices.source_states', [
        'standard' => [OpportunityState::Order->value],
        'deposit' => [OpportunityState::Quotation->value, OpportunityState::Order->value],
        'proforma' => [OpportunityState::Quotation->value, OpportunityState::Order->value],
    ], 'json');
    $this->actingAs(User::factory()->owner()->create());
});

it('shows invoice action on opportunity show when source state allows', function () {
    $opportunity = giCreateOrderOpportunity();

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertSee('Invoice')
        ->assertSee('Generate Deposit');
});

it('disables standard invoice when opportunity is fully invoiced', function () {
    $opportunity = giCreateOrderOpportunity();
    $opportunity->items()->update(['invoiced_quantity' => DB::raw('quantity')]);
    $opportunity->refresh();

    expect($opportunity->isFullyQuantityInvoiced())->toBeTrue();

    Volt::test('opportunities.invoices', ['opportunity' => $opportunity])
        ->assertDontSee('wire:click="generateInvoice"', false)
        ->assertSee('Fully invoiced');
});

it('hides invoice actions when opportunity state is not allowed', function () {
    $created = (new CreateOpportunity)(
        CreateOpportunityData::from([
            'subject' => 'Draft opp',
            'member_id' => Member::factory()->organisation()->create()->id,
            'store_id' => Store::factory()->create()->id,
        ])
    );
    $opportunity = Opportunity::query()->findOrFail($created->id);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertDontSee('wire:click="generateInvoice"', false)
        ->assertDontSee('Generate Deposit');
});
