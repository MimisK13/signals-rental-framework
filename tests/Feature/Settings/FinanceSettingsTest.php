<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\TaxPointPolicy;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use App\Services\SettingsRegistry;
use App\Settings\FinanceSettings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

describe('FinanceSettings definition', function () {
    it('defaults tax_point_policy to supply_date', function () {
        expect(settings('finance.tax_point_policy'))->toBe(TaxPointPolicy::SupplyDate->value);
    });

    it('defaults prices_include_tax_default to false', function () {
        expect(settings('finance.prices_include_tax_default'))->toBeFalse();
    });

    it('defaults show_tax_on_documents to true', function () {
        expect(settings('finance.show_tax_on_documents'))->toBeTrue();
    });

    it('is registered in the settings registry', function () {
        $registry = app(SettingsRegistry::class);

        expect($registry->has('finance'))->toBeTrue()
            ->and($registry->get('finance'))->toBeInstanceOf(FinanceSettings::class);
    });

    it('round-trips valid finance settings', function () {
        settings()->setMany([
            'finance.tax_point_policy' => TaxPointPolicy::InvoiceDate->value,
            'finance.prices_include_tax_default' => ['value' => true, 'type' => 'boolean'],
            'finance.show_tax_on_documents' => ['value' => false, 'type' => 'boolean'],
        ]);

        expect(settings('finance.tax_point_policy'))->toBe('invoice_date')
            ->and(settings('finance.prices_include_tax_default'))->toBeTrue()
            ->and(settings('finance.show_tax_on_documents'))->toBeFalse();
    });

    it('rejects invalid tax_point_policy values via definition rules', function () {
        $validator = Validator::make(
            ['tax_point_policy' => 'order_date'],
            (new FinanceSettings)->rules(),
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('tax_point_policy'))->toBeTrue();
    });
});

describe('FinanceSettings class metadata', function () {
    it('returns finance as the group name', function () {
        expect((new FinanceSettings)->group())->toBe('finance');
    });

    it('declares expected types for all keys', function () {
        expect((new FinanceSettings)->types())
            ->toHaveKey('tax_point_policy', 'string')
            ->toHaveKey('prices_include_tax_default', 'boolean')
            ->toHaveKey('show_tax_on_documents', 'boolean');
    });
});

describe('prices_include_tax_default on opportunity create', function () {
    it('applies the finance default when prices_include_tax is omitted', function () {
        settings()->set('finance.prices_include_tax_default', true);

        $store = Store::factory()->create();
        $member = Member::factory()->organisation()->create();

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Finance default tax mode',
            'member_id' => $member->id,
            'store_id' => $store->id,
            'starts_at' => '2026-07-01T09:00:00Z',
            'ends_at' => '2026-07-02T17:00:00Z',
        ]));

        expect($created->prices_include_tax)->toBeTrue();
    });

    it('honours an explicit prices_include_tax override over the default', function () {
        settings()->set('finance.prices_include_tax_default', true);

        $store = Store::factory()->create();
        $member = Member::factory()->organisation()->create();

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Explicit ex-tax',
            'member_id' => $member->id,
            'store_id' => $store->id,
            'starts_at' => '2026-07-01T09:00:00Z',
            'ends_at' => '2026-07-02T17:00:00Z',
            'prices_include_tax' => false,
        ]));

        expect($created->prices_include_tax)->toBeFalse();
    });
});
