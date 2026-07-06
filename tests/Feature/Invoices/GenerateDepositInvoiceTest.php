<?php

use App\Actions\Invoices\GenerateDepositInvoice;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Data\Invoices\GenerateDepositInvoiceData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Enums\InvoiceType;
use App\Enums\LineItemTransactionType;
use App\Enums\PartialInvoiceTaxPolicy;
use App\Enums\PricingRoundingDirection;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('finance.partial_invoice_tax', PartialInvoiceTaxPolicy::Proportional->value);
});

describe('GenerateDepositInvoice', function () {
    it('generates a 25% deposit with proportional tax penny-exact', function () {
        $opportunity = giCreateQuotationOpportunity();

        $invoice = (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
            'opportunity_id' => $opportunity->id,
            'amount_percentage' => '25',
        ]));

        $row = Invoice::query()->with(['items', 'taxes'])->findOrFail($invoice->id);

        expect($row->type)->toBe(InvoiceType::Deposit->value)
            ->and($row->is_posting)->toBeTrue()
            ->and($row->subtotal)->toBe(2_500)
            ->and($row->tax_total)->toBe(500)
            ->and($row->total)->toBe(3_000)
            ->and($row->items)->toHaveCount(1)
            ->and($row->items->first()->description)->toBe('Deposit')
            ->and($row->items->first()->total)->toBe(2_500)
            ->and($row->items->first()->tax)->toBe(500)
            ->and($row->items->sum('tax'))->toBe($row->taxes->sum('tax_amount'))
            ->and((bool) $opportunity->fresh()->invoiced)->toBeFalse();
    });

    it('generates a fixed-amount deposit derived from the gross ratio', function () {
        $opportunity = giCreateQuotationOpportunity();

        $invoice = (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
            'opportunity_id' => $opportunity->id,
            'amount_fixed' => 3_000,
        ]));

        $row = Invoice::query()->with(['items', 'taxes'])->findOrFail($invoice->id);

        expect($row->subtotal)->toBe(2_500)
            ->and($row->tax_total)->toBe(500)
            ->and($row->total)->toBe(3_000)
            ->and($row->items->sum('tax'))->toBe($row->taxes->sum('tax_amount'));
    });

    it('is idempotent on double submit for the same opportunity', function () {
        $opportunity = giCreateQuotationOpportunity();

        $first = (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
            'opportunity_id' => $opportunity->id,
            'amount_percentage' => '25',
        ]));

        $second = (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
            'opportunity_id' => $opportunity->id,
            'amount_percentage' => '50',
        ]));

        expect($second->id)->toBe($first->id)
            ->and(Invoice::query()->where('opportunity_id', $opportunity->id)->where('type', 'deposit')->count())->toBe(1);
    });

    it('rejects fixed deposit exceeding opportunity total', function () {
        $opportunity = giCreateQuotationOpportunity();

        (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
            'opportunity_id' => $opportunity->id,
            'amount_fixed' => 99_999,
        ]));
    })->throws(ValidationException::class);

    it('rejects deposit from an opportunity in the wrong state', function () {
        $opportunity = giCreateOrderOpportunity();

        settings()->set('invoices.source_states', [
            'deposit' => [],
        ], 'json');

        (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
            'opportunity_id' => $opportunity->id,
            'amount_percentage' => '25',
        ]));
    })->throws(ValidationException::class);
});

describe('GenerateDepositInvoice tax policy matrix', function () {
    it('applies partial-invoice tax policy with penny-exact reconciliation', function (
        string $policy,
        string $percentage,
        int $expectedNet,
        int $expectedTax,
        int $expectedTotal,
    ) {
        settings()->set('finance.partial_invoice_tax', $policy);

        $opportunity = giCreateQuotationOpportunity();

        $invoice = (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
            'opportunity_id' => $opportunity->id,
            'amount_percentage' => $percentage,
        ]));

        $row = Invoice::query()->with(['items', 'taxes'])->findOrFail($invoice->id);

        expect($row->subtotal)->toBe($expectedNet)
            ->and($row->tax_total)->toBe($expectedTax)
            ->and($row->total)->toBe($expectedTotal)
            ->and($row->items->sum('tax'))->toBe($row->taxes->sum('tax_amount'));
    })->with([
        'proportional 25%' => [
            PartialInvoiceTaxPolicy::Proportional->value,
            '25',
            2_500,
            500,
            3_000,
        ],
        'full 25%' => [
            PartialInvoiceTaxPolicy::Full->value,
            '25',
            2_500,
            2_000,
            4_500,
        ],
    ]);

    it('maintains penny reconciliation across multiple tax rate groups', function () {
        $zones = giWireZoneTaxInfrastructure();
        $opportunity = giCreateQuotationOpportunity([], [], $zones);

        $product2 = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass2']->id]);
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $product2->name,
            'itemable_id' => $product2->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'unit_price' => 5_000,
            'transaction_type' => LineItemTransactionType::Sale->value,
        ]));

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));

        settings()->set('finance.partial_invoice_tax', PartialInvoiceTaxPolicy::Proportional->value);

        $invoice = (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
            'opportunity_id' => $opportunity->fresh()->id,
            'amount_percentage' => '25',
        ]));

        $row = Invoice::query()->with(['items', 'taxes'])->findOrFail($invoice->id);

        expect($row->taxes->count())->toBeGreaterThan(0)
            ->and($row->items->sum('tax'))->toBe($row->taxes->sum('tax_amount'))
            ->and($row->total)->toBe($row->subtotal + $row->tax_total);
    });
});
