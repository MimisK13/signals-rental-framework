<?php

use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityState;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->store = Store::factory()->create();
});

it('protects the experimental quotations routes from guests', function (): void {
    $this->get(route('experimental.quotations.index'))->assertRedirect();
    $this->get(route('experimental.quotations.create'))->assertRedirect();
});

it('renders the quotations workspace and excludes non-quotations', function (): void {
    $this->actingAs($this->user);

    $draft = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Experimental draft',
        'store_id' => $this->store->id,
    ]));
    $quotation = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Experimental quotation',
        'store_id' => $this->store->id,
    ]));
    (new ConvertToQuotation)(Opportunity::query()->findOrFail($quotation->id));

    Volt::test('experimental.quotations.index')
        ->assertSee('Experimental quotation')
        ->assertDontSee('Experimental draft');

    expect($draft->id)->not->toBe($quotation->id);
});

it('rejects a draft from the quotation editor', function (): void {
    $this->actingAs($this->user);

    $draft = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Draft only',
        'store_id' => $this->store->id,
    ]));

    $this->withoutMiddleware();

    $this->get(route('experimental.quotations.edit', $draft->id))->assertNotFound();
});

it('creates a quotation through canonical opportunity actions', function (): void {
    $this->actingAs($this->user);

    Volt::test('experimental.quotations.create')
        ->set('subject', 'New experimental quotation')
        ->set('storeId', $this->store->id)
        ->call('save')
        ->assertRedirect();

    expect(Opportunity::query()->where('subject', 'New experimental quotation')->value('state'))
        ->toBe(OpportunityState::Quotation);
});
