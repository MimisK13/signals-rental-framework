<?php

use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Availability\Shortages\Models\ShortageResolutionItem;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\VirtualStock\Actions\ConfirmVirtualStockIntake;
use App\Domain\VirtualStock\Actions\SetVirtualStockCostOverride;
use App\Domain\VirtualStock\Data\SetVirtualStockCostOverrideData;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use App\Domain\VirtualStock\Presentation\Livewire\Form;
use App\Domain\VirtualStock\Presentation\Livewire\Index;
use App\Domain\VirtualStock\Presentation\Livewire\Show;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the virtual stock index page', function () {
    VirtualStockIntake::factory()->create();

    $this->get(route('virtual-stock.index'))
        ->assertOk();

    Livewire::test(Index::class)
        ->assertSee('Virtual Stock')
        ->assertSee('Catalogue');
});

it('renders the virtual stock show page', function () {
    $intake = createTestVirtualStockIntake();

    Livewire::test(Show::class, ['virtualStockIntake' => $intake])
        ->assertSee($intake->number);
});

it('shows cost apportionment panel after confirm', function () {
    $intake = createTestVirtualStockIntake(['quantity' => 2, 'unit_cost' => 1500]);
    $opportunity = Opportunity::factory()->create();
    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'metadata' => ['opportunity_id' => $opportunity->id],
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($opportunity)->create()->id,
        'quantity_allocated' => 2,
    ]);

    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    Livewire::test(Show::class, ['virtualStockIntake' => $intake->refresh()])
        ->assertSee('Cost apportionment')
        ->assertSee('Primary job');
});

it('shows a warning when manual overrides do not sum to the intake total', function () {
    config(['shortages.cost_apportionment_strategy' => 'manual']);

    $intake = createTestVirtualStockIntake(['quantity' => 3, 'unit_cost' => 1000]);
    $opportunity = Opportunity::factory()->create();
    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($opportunity)->create()->id,
        'quantity_allocated' => 2,
    ]);

    app(ConfirmVirtualStockIntake::class)($intake->refresh());

    app(SetVirtualStockCostOverride::class)(
        $intake->refresh(),
        SetVirtualStockCostOverrideData::from([
            'opportunity_id' => $opportunity->id,
            'unit_cost_override' => 100,
        ]),
    );

    Livewire::test(Show::class, ['virtualStockIntake' => $intake->refresh()])
        ->assertSee('Manual cost overrides do not sum to the intake total');
});

it('renders the create form', function () {
    $this->get(route('virtual-stock.create'))->assertOk();

    Livewire::test(Form::class)
        ->assertSee('New Virtual Stock Intake');
});
