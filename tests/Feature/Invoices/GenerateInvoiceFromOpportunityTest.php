<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\GenerateProformaInvoice;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\GenerateProformaInvoiceData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\InvoiceType;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityState;
use App\Enums\PricingRoundingDirection;
use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Invoices\InvoiceSourceStateRule;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

describe('GenerateInvoiceFromOpportunity', function () {
    it('generates a standard invoice from an order matching charge_including_tax_total', function () {
        $opportunity = giCreateOrderOpportunity();

        $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
            'type' => InvoiceType::Standard->value,
        ]));

        expect($invoice->total)->toBe($opportunity->formatMoneyCost('charge_including_tax_total'))
            ->and($invoice->tax_total)->toBe($opportunity->formatMoneyCost('tax_total'))
            ->and($invoice->subtotal)->toBe($opportunity->formatMoneyCost('charge_total'))
            ->and($invoice->opportunity_id)->toBe($opportunity->id);

        $row = Invoice::query()->with(['items', 'taxes'])->findOrFail($invoice->id);

        expect($row->total)->toBe((int) $opportunity->charge_including_tax_total)
            ->and($row->tax_total)->toBe((int) $opportunity->tax_total)
            ->and($row->items)->toHaveCount(1)
            ->and($row->taxes)->toHaveCount(1)
            ->and($row->items->first()->tax)->toBe((int) $opportunity->tax_total)
            ->and((bool) $opportunity->fresh()->invoiced)->toBeTrue();
    });

    it('is idempotent on double submit for the same opportunity and type', function () {
        $opportunity = giCreateOrderOpportunity();

        $first = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $second = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        expect($second->id)->toBe($first->id)
            ->and(Invoice::query()->where('opportunity_id', $opportunity->id)->count())->toBe(1);
    });

    it('rejects standard invoice from a quotation per default source-state rules', function () {
        $zones = giWireZoneTaxInfrastructure();
        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id]);
        $member = Member::factory()->organisation()->create(['sale_tax_class_id' => $zones['orgClass']->id]);

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Quote only',
            'member_id' => $member->id,
            'store_id' => $store->id,
        ]));
        (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

        (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $created->id,
            'type' => InvoiceType::Standard->value,
        ]));
    })->throws(ValidationException::class);

    it('allows standard invoice from quotation when source-state setting is flipped', function () {
        $zones = giWireZoneTaxInfrastructure();
        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['uk']->id,
            'buyer_type' => TaxBuyerType::B2c->value,
        ]);

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Quote invoice',
            'member_id' => $member->id,
            'store_id' => $store->id,
        ]));
        (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

        $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);
        (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'transaction_type' => LineItemTransactionType::Sale->value,
        ]));

        $opportunity = Opportunity::findOrFail($created->id);
        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));

        settings()->set('invoices.source_states', [
            'standard' => [OpportunityState::Quotation->value, OpportunityState::Order->value],
            'deposit' => [OpportunityState::Quotation->value, OpportunityState::Order->value],
            'proforma' => [OpportunityState::Quotation->value, OpportunityState::Order->value],
        ], 'json');

        $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        expect($invoice->id)->toBeGreaterThan(0);
    });

    it('leaves the invoice unchanged when opportunity lines are edited after checkout', function () {
        $opportunity = giCreateOrderOpportunity();

        $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $beforeTotal = Invoice::query()->findOrFail($invoice->id)->total;

        OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->update(['total' => 999_999]);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));

        expect((int) $opportunity->fresh()->charge_including_tax_total)->not->toBe($beforeTotal)
            ->and(Invoice::query()->findOrFail($invoice->id)->total)->toBe($beforeTotal);
    });

    it('copies matching custom fields from opportunity to invoice', function () {
        $opportunity = giCreateOrderOpportunity();

        $oppField = CustomField::factory()->forModule('Opportunity')->string()->create([
            'name' => 'po_reference',
        ]);
        $invField = CustomField::factory()->forModule('Invoice')->string()->create([
            'name' => 'po_reference',
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $oppField->id,
            'entity_type' => $opportunity->getMorphClass(),
            'entity_id' => $opportunity->id,
            'value_string' => 'PO-999',
        ]);

        $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_id' => $invField->id,
            'entity_type' => (new Invoice)->getMorphClass(),
            'entity_id' => $invoice->id,
            'value_string' => 'PO-999',
        ]);
    });

    it('scopes snapshot to the active quote version only', function () {
        $zones = giWireZoneTaxInfrastructure();
        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['uk']->id,
        ]);
        $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Version scoped quote',
            'member_id' => $member->id,
            'store_id' => $store->id,
        ]));
        (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

        $opportunity = Opportunity::findOrFail($created->id);

        $supersededVersion = OpportunityVersion::factory()->create([
            'opportunity_id' => $opportunity->id,
        ]);
        $activeVersion = OpportunityVersion::factory()->create([
            'opportunity_id' => $opportunity->id,
        ]);

        Opportunity::query()->whereKey($opportunity->id)->update([
            'active_version_id' => $activeVersion->id,
            'version_count' => 2,
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'version_id' => $activeVersion->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'total' => 50_000,
            'unit_price' => 50_000,
            'quantity' => '1',
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'version_id' => $supersededVersion->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'total' => 1_000,
            'unit_price' => 1_000,
            'quantity' => '1',
        ]);

        $opportunity = Opportunity::query()->findOrFail($opportunity->id);
        app(OpportunityTotalsCalculator::class)->rollUp(
            $opportunity->fresh(['items.itemSurcharges', 'items.item', 'costs', 'member']),
        );
        $opportunity->refresh();

        $invoice = (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $items = Invoice::query()->findOrFail($invoice->id)->items;

        expect($items)->toHaveCount(1)
            ->and($items->first()->total)->toBe(50_000);
    });

    it('forbids users without invoices.create permission', function () {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('invoices.view');
        $this->actingAs($viewer);

        $opportunity = giCreateOrderOpportunity();

        (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));
    })->throws(AuthorizationException::class);
});

describe('GenerateInvoiceFromOpportunity tax matrix', function () {
    it('reproduces standard domestic VAT totals', function () {
        $opportunity = giCreateOrderOpportunity();

        $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        expect(Invoice::query()->findOrFail($invoice->id)->total)
            ->toBe((int) $opportunity->charge_including_tax_total)
            ->and(Invoice::query()->findOrFail($invoice->id)->tax_total)->toBe(2_000);
    });

    it('reproduces reverse charge zero-tax totals with treatment marker', function () {
        $zones = giWireZoneTaxInfrastructure();
        $opportunity = giCreateOrderOpportunity([
            'tax_zone_id' => $zones['euMember']->id,
            'buyer_type' => TaxBuyerType::B2b->value,
            'tax_number' => 'DE123456789',
            'tax_number_validated' => true,
        ], [], $zones);

        $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $row = Invoice::query()->with('items')->findOrFail($invoice->id);

        expect($row->tax_total)->toBe(0)
            ->and($row->total)->toBe((int) $opportunity->charge_including_tax_total)
            ->and($row->items->first()->tax_treatment)->toBe(TaxResultType::ReverseCharge->value);
    });

    it('reproduces zero-rated export totals', function () {
        $zones = giWireZoneTaxInfrastructure();
        $opportunity = giCreateOrderOpportunity([
            'tax_zone_id' => $zones['world']->id,
            'buyer_type' => TaxBuyerType::B2b->value,
        ], [], $zones);

        $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $row = Invoice::query()->with('items')->findOrFail($invoice->id);

        expect($row->tax_total)->toBe(0)
            ->and($row->items->first()->tax_treatment)->toBe(TaxResultType::ZeroRated->value);
    });

    it('reproduces exempt buyer totals', function () {
        $opportunity = giCreateOrderOpportunity([
            'tax_exempt' => true,
        ]);

        $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $row = Invoice::query()->with('items')->findOrFail($invoice->id);

        expect($row->tax_total)->toBe(0)
            ->and($row->items->first()->tax_treatment)->toBe(TaxResultType::Exempt->value);
    });

    it('maintains penny reconciliation between line tax and group tax sums', function () {
        $zones = giWireZoneTaxInfrastructure();
        $opportunity = giCreateOrderOpportunity([], [], $zones);

        $product2 = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass2']->id]);
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $product2->name,
            'itemable_id' => $product2->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'transaction_type' => LineItemTransactionType::Sale->value,
        ]));

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));

        $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->fresh()->id,
        ]));

        $row = Invoice::query()->with(['items', 'taxes'])->findOrFail($invoice->id);

        expect($row->items->sum('tax'))->toBe($row->taxes->sum('tax_amount'))
            ->and($row->total)->toBe((int) $opportunity->fresh()->charge_including_tax_total);
    });
});

describe('InvoiceSourceStateRule', function () {
    it('returns seeded defaults when settings key is absent', function () {
        $rule = app(InvoiceSourceStateRule::class);

        expect($rule->defaultRules()['standard'])
            ->toBe([OpportunityState::Order->value])
            ->and($rule->allowedStatesFor(InvoiceType::Standard))
            ->toContain(OpportunityState::Order->value);
    });
});
