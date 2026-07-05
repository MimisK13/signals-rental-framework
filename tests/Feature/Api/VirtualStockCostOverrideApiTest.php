<?php

use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\User;
use App\Models\VirtualStockCostOverride;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
    $this->token = $this->owner->createToken('test', ['virtual_stock:write', 'virtual_stock:read'])->plainTextToken;

    $this->intake = createTestVirtualStockIntake([
        'quantity' => 2,
        'unit_cost' => 1500,
    ]);

    $opportunity = Opportunity::factory()->create();
    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $this->intake->id,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($opportunity)->create()->id,
        'quantity_allocated' => 2,
    ]);

    app(ConfirmVirtualStockIntake::class)($this->intake->refresh());
});

it('sets a cost override via api', function () {
    $opportunity = Opportunity::factory()->create();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/virtual_stock_intakes/{$this->intake->id}/cost_overrides", [
            'opportunity_id' => $opportunity->id,
            'unit_cost_override' => 900,
        ])
        ->assertSuccessful()
        ->assertJsonPath('apportionment.strategy_key', 'primary_job');

    expect(VirtualStockCostOverride::query()->where([
        'virtual_stock_intake_id' => $this->intake->id,
        'opportunity_id' => $opportunity->id,
    ])->value('unit_cost_override'))->toBe(900);
});

it('clears a cost override via api', function () {
    $opportunity = Opportunity::factory()->create();

    VirtualStockCostOverride::factory()->create([
        'virtual_stock_intake_id' => $this->intake->id,
        'opportunity_id' => $opportunity->id,
        'unit_cost_override' => 500,
    ]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->deleteJson("/api/v1/virtual_stock_intakes/{$this->intake->id}/cost_overrides/{$opportunity->id}")
        ->assertSuccessful();

    expect(VirtualStockCostOverride::query()->where([
        'virtual_stock_intake_id' => $this->intake->id,
        'opportunity_id' => $opportunity->id,
    ])->exists())->toBeFalse();
});

it('returns 403 without costs.view on override write', function () {
    $user = User::factory()->create(['member_id' => $this->owner->member_id]);
    $user->givePermissionTo(['virtual_stock.view', 'virtual_stock.edit']);
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $this->intake->store_id,
        'created_at' => now(),
    ]);
    $token = $user->createToken('test', ['virtual_stock:write'])->plainTextToken;
    $opportunity = Opportunity::factory()->create();

    Auth::logout();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/virtual_stock_intakes/{$this->intake->id}/cost_overrides", [
            'opportunity_id' => $opportunity->id,
            'unit_cost_override' => 900,
        ])
        ->assertForbidden();
});

it('apportions via api when costs.view is present', function () {
    config(['shortages.cost_apportionment_strategy' => 'primary_job']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/virtual_stock_intakes/{$this->intake->id}/apportion", [
            'strategy' => 'even_split',
        ])
        ->assertSuccessful()
        ->assertJsonPath('apportionment.strategy_key', 'even_split');
});

it('returns manual_mismatch_warning in the apportionment api payload', function () {
    config(['shortages.cost_apportionment_strategy' => 'manual']);

    $opportunity = Opportunity::factory()->create();
    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $this->intake->id,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($opportunity)->create()->id,
        'quantity_allocated' => 2,
    ]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/virtual_stock_intakes/{$this->intake->id}/cost_overrides", [
            'opportunity_id' => $opportunity->id,
            'unit_cost_override' => 100,
        ])
        ->assertSuccessful()
        ->assertJsonPath('apportionment.manual_mismatch_warning', true);
});
