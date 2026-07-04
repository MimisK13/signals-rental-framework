<?php

use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Models\TaxCategory;
use App\Models\TaxRule;
use App\Models\TaxZone;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->actingAs(User::factory()->owner()->create());
});

describe('tax zones admin', function () {
    it('renders the tax zones page', function () {
        $this->get(route('admin.settings.tax.zones'))
            ->assertOk()
            ->assertSee('Tax Zones');
    });

    it('lists tax zones', function () {
        TaxZone::factory()->create(['name' => 'United Kingdom']);
        TaxZone::factory()->create(['name' => 'EU Member States']);

        Volt::test('admin.settings.tax.zones')
            ->assertSee('United Kingdom')
            ->assertSee('EU Member States');
    });

    it('can create a tax zone', function () {
        Volt::test('admin.settings.tax.zone-form')
            ->set('name', 'Nordics')
            ->set('slug', 'nordics')
            ->set('priority', 40)
            ->call('save')
            ->assertHasNoErrors();

        expect(TaxZone::where('slug', 'nordics')->exists())->toBeTrue();
    });

    it('can edit a tax zone', function () {
        $zone = TaxZone::factory()->create(['name' => 'Old Name', 'slug' => 'old_name']);

        Volt::test('admin.settings.tax.zone-form', ['taxZone' => $zone])
            ->assertSet('name', 'Old Name')
            ->set('name', 'New Name')
            ->call('save');

        expect($zone->fresh()->name)->toBe('New Name');
    });

    it('can delete a tax zone', function () {
        $zone = TaxZone::factory()->create(['name' => 'To Delete']);

        Volt::test('admin.settings.tax.zones')
            ->call('deleteTaxZone', $zone->id);

        expect(TaxZone::where('name', 'To Delete')->exists())->toBeFalse();
    });
});

describe('tax categories admin', function () {
    it('renders the tax categories page', function () {
        $this->get(route('admin.settings.tax.categories'))
            ->assertOk()
            ->assertSee('Tax Categories');
    });

    it('can create a tax category', function () {
        Volt::test('admin.settings.tax.category-form')
            ->set('name', 'Special Rate')
            ->set('slug', 'special_rate')
            ->set('type', 'reduced')
            ->call('save')
            ->assertHasNoErrors();

        expect(TaxCategory::where('slug', 'special_rate')->exists())->toBeTrue();
    });

    it('can delete a tax category', function () {
        $category = TaxCategory::factory()->create(['name' => 'To Delete']);

        Volt::test('admin.settings.tax.categories')
            ->call('deleteCategory', $category->id);

        expect(TaxCategory::where('name', 'To Delete')->exists())->toBeFalse();
    });
});

describe('zone tax rules admin', function () {
    it('renders the create zone rule form', function () {
        $this->get(route('admin.settings.tax.rules.create-zone'))
            ->assertOk()
            ->assertSee('Create Zone Tax Rule');
    });

    it('can create a zone tax rule', function () {
        $seller = TaxZone::factory()->uk()->create();
        $buyer = TaxZone::factory()->euMember()->create();

        Volt::test('admin.settings.tax.zone-rule-form')
            ->set('name', 'UK to EU B2B RC')
            ->set('sellerZoneId', $seller->id)
            ->set('buyerZoneId', $buyer->id)
            ->set('buyerType', TaxBuyerType::Business->value)
            ->set('hasTaxId', true)
            ->set('resultType', TaxResultType::ReverseCharge->value)
            ->set('priority', 90)
            ->call('save')
            ->assertHasNoErrors();

        $rule = TaxRule::query()->where('name', 'UK to EU B2B RC')->first();
        expect($rule)->not->toBeNull()
            ->and($rule->isZoneRule())->toBeTrue()
            ->and($rule->result_type)->toBe(TaxResultType::ReverseCharge);
    });

    it('can edit a zone tax rule', function () {
        $rule = TaxRule::factory()->zoneMatch(['name' => 'Original'])->create();

        Volt::test('admin.settings.tax.zone-rule-form', ['taxRule' => $rule])
            ->assertSet('name', 'Original')
            ->set('name', 'Updated Rule')
            ->call('save');

        expect($rule->fresh()->name)->toBe('Updated Rule');
    });
});

it('returns 403 for non-admin users on tax zones', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.tax.zones'))
        ->assertForbidden();
});
