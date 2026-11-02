<?php

use App\Domain\Tax\Actions\CreateTaxRate;
use App\Domain\Tax\Actions\CreateTaxRule;
use App\Domain\Tax\Actions\CreateTaxZone;
use App\Domain\Tax\Actions\CreateZoneTaxRule;
use App\Domain\Tax\Actions\DeleteTaxCategory;
use App\Domain\Tax\Actions\DeleteTaxRate;
use App\Domain\Tax\Actions\DeleteTaxRule;
use App\Domain\Tax\Actions\DeleteTaxZone;
use App\Domain\Tax\Actions\UpdateTaxCategory;
use App\Domain\Tax\Actions\UpdateTaxRate;
use App\Domain\Tax\Actions\UpdateTaxRule;
use App\Domain\Tax\Actions\UpdateTaxZone;
use App\Domain\Tax\Actions\UpdateZoneTaxRule;
use App\Domain\Tax\Data\CreateTaxRateData;
use App\Domain\Tax\Data\CreateTaxRuleData;
use App\Domain\Tax\Data\CreateTaxZoneData;
use App\Domain\Tax\Data\CreateZoneTaxRuleData;
use App\Domain\Tax\Data\UpdateTaxCategoryData;
use App\Domain\Tax\Data\UpdateTaxRateData;
use App\Domain\Tax\Data\UpdateTaxRuleData;
use App\Domain\Tax\Data\UpdateTaxZoneData;
use App\Domain\Tax\Data\UpdateZoneTaxRuleData;
use App\Domain\Tax\Enums\TaxBuyerType;
use App\Domain\Tax\Enums\TaxResultType;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxCategory;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxRule;
use App\Domain\Tax\Models\TaxZone;
use App\Models\ActionLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

/**
 * End-to-end audit-trail coverage for tax actions.
 *
 * These intentionally do NOT fake AuditableEvent, so the auto-discovered
 * LogAction listener runs and writes an action_logs row. The queue is faked
 * only to suppress real webhook delivery.
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
    Queue::fake();
});

it('records an action_logs row when a tax rate is created', function () {
    $rate = (new CreateTaxRate)(CreateTaxRateData::from([
        'name' => 'Audited Rate',
        'rate' => '20.0000',
    ]));

    assertActionLogged('tax_rate.created', TaxRate::class, $rate->id, $this->owner->id);
});

it('records an action_logs row when a tax rate is updated', function () {
    $rate = TaxRate::factory()->create();

    (new UpdateTaxRate)($rate, UpdateTaxRateData::from(['name' => 'Renamed Rate']));

    assertActionLogged('tax_rate.updated', TaxRate::class, $rate->id, $this->owner->id);
});

it('records an action_logs row when a tax rate is deleted', function () {
    $rate = TaxRate::factory()->create();

    (new DeleteTaxRate)($rate);

    assertActionLogged('tax_rate.deleted', TaxRate::class, $rate->id, $this->owner->id);
});

it('records an action_logs row when a tax rule is created', function () {
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create();
    $rate = TaxRate::factory()->create();

    $rule = (new CreateTaxRule)(CreateTaxRuleData::from([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $rate->id,
    ]));

    assertActionLogged('tax_rule.created', TaxRule::class, $rule->id, $this->owner->id);
});

it('records an action_logs row when a tax rule is updated', function () {
    $rule = TaxRule::factory()->create();

    (new UpdateTaxRule)($rule, UpdateTaxRuleData::from(['priority' => 5]));

    assertActionLogged('tax_rule.updated', TaxRule::class, $rule->id, $this->owner->id);
});

it('records an action_logs row when a tax rule is deleted', function () {
    $rule = TaxRule::factory()->create();

    (new DeleteTaxRule)($rule);

    assertActionLogged('tax_rule.deleted', TaxRule::class, $rule->id, $this->owner->id);
});

it('records old and new values when a tax zone is updated', function () {
    $zone = TaxZone::factory()->create(['name' => 'Original Zone']);

    (new UpdateTaxZone)($zone, UpdateTaxZoneData::from(['name' => 'Renamed Zone']));

    $log = ActionLog::query()
        ->where('action', 'tax_zone.updated')
        ->where('auditable_type', TaxZone::class)
        ->where('auditable_id', $zone->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toBe(['name' => 'Original Zone'])
        ->and($log->new_values)->toBe(['name' => 'Renamed Zone']);
});

it('records new values when a tax zone is created', function () {
    $zone = (new CreateTaxZone)(CreateTaxZoneData::from([
        'name' => 'Audit Zone',
        'slug' => 'audit_zone',
    ]));

    $log = ActionLog::query()
        ->where('action', 'tax_zone.created')
        ->where('auditable_id', $zone->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toBeNull()
        ->and($log->new_values)->toMatchArray(['name' => 'Audit Zone', 'slug' => 'audit_zone']);
});

it('records old values when a tax zone is deleted', function () {
    $zone = TaxZone::factory()->create(['name' => 'Deleted Zone']);

    (new DeleteTaxZone)($zone);

    $log = ActionLog::query()
        ->where('action', 'tax_zone.deleted')
        ->where('auditable_id', $zone->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toMatchArray(['name' => 'Deleted Zone'])
        ->and($log->new_values)->toBeNull();
});

it('records old and new values when a tax category is updated', function () {
    $category = TaxCategory::factory()->standard()->create(['name' => 'Standard Goods']);

    (new UpdateTaxCategory)($category, UpdateTaxCategoryData::from(['name' => 'Standard Supplies']));

    $log = ActionLog::query()
        ->where('action', 'tax_category.updated')
        ->where('auditable_type', TaxCategory::class)
        ->where('auditable_id', $category->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toBe(['name' => 'Standard Goods'])
        ->and($log->new_values)->toBe(['name' => 'Standard Supplies']);
});

it('records new values when a zone tax rule is created', function () {
    $seller = TaxZone::factory()->create();
    $buyer = TaxZone::factory()->create();

    $rule = (new CreateZoneTaxRule)(CreateZoneTaxRuleData::from([
        'name' => 'UK to EU reverse charge',
        'seller_zone_id' => $seller->id,
        'buyer_zone_id' => $buyer->id,
        'buyer_type' => TaxBuyerType::Business,
        'has_tax_id' => true,
        'result_type' => TaxResultType::ReverseCharge,
        'legal_text' => 'Reverse charge applies.',
    ]));

    $log = ActionLog::query()
        ->where('action', 'tax_rule.created')
        ->where('auditable_id', $rule->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toBeNull()
        ->and($log->new_values)->toMatchArray([
            'name' => 'UK to EU reverse charge',
            'seller_zone_id' => $seller->id,
            'buyer_zone_id' => $buyer->id,
            'result_type' => TaxResultType::ReverseCharge->value,
        ]);
});

it('records old and new values when a zone tax rule is updated', function () {
    $seller = TaxZone::factory()->create();
    $buyer = TaxZone::factory()->create();

    $rule = TaxRule::factory()->zoneMatch()->create([
        'name' => 'Old rule',
        'seller_zone_id' => $seller->id,
        'buyer_zone_id' => $buyer->id,
        'result_type' => TaxResultType::ReverseCharge,
    ]);

    (new UpdateZoneTaxRule)($rule, UpdateZoneTaxRuleData::from(['name' => 'Updated rule']));

    $log = ActionLog::query()
        ->where('action', 'tax_rule.updated')
        ->where('auditable_id', $rule->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toBe(['name' => 'Old rule'])
        ->and($log->new_values)->toBe(['name' => 'Updated rule']);
});

it('records old values when a tax category is deleted', function () {
    $category = TaxCategory::factory()->reduced()->create();

    (new DeleteTaxCategory)($category);

    $log = ActionLog::query()
        ->where('action', 'tax_category.deleted')
        ->where('auditable_id', $category->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toMatchArray(['slug' => 'reduced'])
        ->and($log->new_values)->toBeNull();
});
