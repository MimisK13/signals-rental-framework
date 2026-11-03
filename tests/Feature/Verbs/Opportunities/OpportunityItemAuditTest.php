<?php

use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ChangeItemDates;
use App\Domain\Projects\Opportunities\Actions\ChangeItemQuantity;
use App\Domain\Projects\Opportunities\Actions\ClearDealPrice;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\OverrideItemPrice;
use App\Domain\Projects\Opportunities\Actions\RemoveOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\SetDealPrice;
use App\Domain\Projects\Opportunities\Actions\SetItemDiscount;
use App\Domain\Projects\Opportunities\Actions\SubstituteItem;
use App\Domain\Projects\Opportunities\Actions\ToggleItemOptional;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\ChangeItemDatesData;
use App\Domain\Projects\Opportunities\Data\ChangeItemQuantityData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\OverrideItemPriceData;
use App\Domain\Projects\Opportunities\Data\SetDealPriceData;
use App\Domain\Projects\Opportunities\Data\SetItemDiscountData;
use App\Domain\Projects\Opportunities\Data\SubstituteItemData;
use App\Domain\Projects\Opportunities\Data\ToggleItemOptionalData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

function auditOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Audited items']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('writes an audit row against the opportunity for each item mutation', function () {
    $opportunity = auditOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Speaker', 'quantity' => '2', 'unit_price' => 5000,
    ]));
    $item = $opportunity->items()->firstOrFail();

    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '3']));
    (new OverrideItemPrice)($item->refresh(), OverrideItemPriceData::from(['unit_price' => 6000]));
    (new SetItemDiscount)($item->refresh(), SetItemDiscountData::from(['discount_percent' => '5']));
    (new ToggleItemOptional)($item->refresh(), ToggleItemOptionalData::from(['is_optional' => true]));
    (new SetDealPrice)($opportunity->refresh(), SetDealPriceData::from(['deal_total' => 9000]));
    (new ClearDealPrice)($opportunity->refresh());

    $actions = ActionLog::query()
        ->where('auditable_type', Opportunity::class)
        ->where('auditable_id', $opportunity->id)
        ->pluck('action')
        ->all();

    expect($actions)->toContain('opportunity.item_added')
        ->toContain('opportunity.item_quantity_changed')
        ->toContain('opportunity.item_price_overridden')
        ->toContain('opportunity.item_discount_set')
        ->toContain('opportunity.item_optional_toggled')
        ->toContain('opportunity.deal_price_set')
        ->toContain('opportunity.deal_price_cleared');

    // Every item-audit row carries the verb_event_id (replay dedup key).
    $itemAdded = ActionLog::query()
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.item_added')
        ->sole();
    expect($itemAdded->verb_event_id)->not->toBeNull()
        ->and($itemAdded->user_id)->toBe($this->actor->id);
});

it('writes audit rows for item date changes and substitutions', function () {
    $opportunity = auditOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Mixer', 'quantity' => '1', 'unit_price' => 7000,
    ]));
    $item = $opportunity->items()->firstOrFail();

    (new ChangeItemDates)($item->refresh(), ChangeItemDatesData::from([
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    (new SubstituteItem)($item->refresh(), SubstituteItemData::from([
        'name' => 'Larger Mixer',
    ]));

    $actions = ActionLog::query()
        ->where('auditable_type', Opportunity::class)
        ->where('auditable_id', $opportunity->id)
        ->pluck('action')
        ->all();

    expect($actions)->toContain('opportunity.item_dates_changed')
        ->toContain('opportunity.item_substituted');

    $datesChanged = ActionLog::query()
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.item_dates_changed')
        ->sole();
    expect($datesChanged->verb_event_id)->not->toBeNull()
        ->and($datesChanged->user_id)->toBe($this->actor->id);

    $substituted = ActionLog::query()
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.item_substituted')
        ->sole();
    expect($substituted->verb_event_id)->not->toBeNull()
        ->and($substituted->user_id)->toBe($this->actor->id);
});

it('records a removal audit row with the removed item snapshot', function () {
    $opportunity = auditOpportunity();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Cable', 'quantity' => '1', 'unit_price' => 1000,
    ]));
    $item = $opportunity->items()->firstOrFail();

    (new RemoveOpportunityItem)($item->refresh());

    $removed = ActionLog::query()
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.item_removed')
        ->sole();

    /** @var array<string, mixed>|null $oldValues */
    $oldValues = $removed->old_values;

    expect($removed->new_values)->toBeNull();
    expect($oldValues)->not->toBeNull();
    expect($oldValues['name'] ?? null)->toBe('Cable');
});

it('does not duplicate item audit rows on replay', function () {
    $opportunity = auditOpportunity();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Truss', 'quantity' => '2', 'unit_price' => 4000,
    ]));
    $item = $opportunity->items()->firstOrFail();
    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '5']));

    $countBefore = ActionLog::query()->where('auditable_id', $opportunity->id)->count();

    Verbs::replay();

    expect(ActionLog::query()->where('auditable_id', $opportunity->id)->count())->toBe($countBefore);
});
