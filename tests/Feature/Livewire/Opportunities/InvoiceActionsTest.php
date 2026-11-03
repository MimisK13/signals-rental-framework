<?php

use App\Domain\Invoices\Presentation\Livewire\OpportunityInvoices;
use App\Domain\Members\Models\Member;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Enums\OpportunityState;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Show;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

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

    Livewire::test(Show::class, ['opportunity' => $opportunity])
        ->assertSee('Invoice')
        ->assertSee('Generate Deposit');
});

it('disables standard invoice when opportunity is fully invoiced', function () {
    $opportunity = giCreateOrderOpportunity();
    $opportunity->items()->update(['invoiced_quantity' => DB::raw('quantity')]);
    $opportunity->refresh();

    expect($opportunity->isFullyQuantityInvoiced())->toBeTrue();

    Livewire::test(OpportunityInvoices::class, ['opportunity' => $opportunity])
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

    Livewire::test(Show::class, ['opportunity' => $opportunity])
        ->assertDontSee('wire:click="generateInvoice"', false)
        ->assertDontSee('Generate Deposit');
});
