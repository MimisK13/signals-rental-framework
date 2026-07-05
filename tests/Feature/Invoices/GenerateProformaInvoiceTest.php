<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\GenerateProformaInvoice;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\GenerateProformaInvoiceData;
use App\Enums\InvoiceType;
use App\Enums\PricingRoundingDirection;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

describe('GenerateProformaInvoice', function () {
    it('generates a full snapshot pro-forma from a quotation', function () {
        $opportunity = giCreateQuotationOpportunity();

        $invoice = (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $row = Invoice::query()->with(['items', 'taxes'])->findOrFail($invoice->id);

        expect($row->type)->toBe(InvoiceType::Proforma->value)
            ->and($row->is_posting)->toBeFalse()
            ->and($row->total)->toBe((int) $opportunity->charge_including_tax_total)
            ->and($row->tax_total)->toBe((int) $opportunity->tax_total)
            ->and($row->subtotal)->toBe((int) $opportunity->charge_total)
            ->and($row->items)->not->toBeEmpty()
            ->and($row->taxes)->not->toBeEmpty()
            ->and($row->items->sum('tax'))->toBe($row->taxes->sum('tax_amount'))
            ->and((bool) $opportunity->fresh()->invoiced)->toBeFalse();
    });

    it('does not advance the standard invoice sequence counter', function () {
        $opportunity = giCreateQuotationOpportunity();
        $storeId = (int) $opportunity->store_id;

        DocumentSequence::factory()->create([
            'store_id' => $storeId,
            'document_type' => InvoiceType::Standard->documentSequenceType(),
            'prefix' => 'S-',
            'include_year' => false,
            'padding' => 3,
            'next_number' => 10,
        ]);

        DocumentSequence::factory()->create([
            'store_id' => $storeId,
            'document_type' => InvoiceType::Proforma->documentSequenceType(),
            'prefix' => 'P-',
            'include_year' => false,
            'padding' => 3,
            'next_number' => 3,
        ]);

        $invoice = (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $standardSequence = DocumentSequence::query()
            ->where('store_id', $storeId)
            ->where('document_type', InvoiceType::Standard->documentSequenceType())
            ->firstOrFail();

        $proformaSequence = DocumentSequence::query()
            ->where('store_id', $storeId)
            ->where('document_type', InvoiceType::Proforma->documentSequenceType())
            ->firstOrFail();

        expect($standardSequence->next_number)->toBe(10)
            ->and($proformaSequence->next_number)->toBe(4)
            ->and($invoice->number)->toStartWith('P-');
    });

    it('allows standard and pro-forma invoices for the same opportunity independently', function () {
        $opportunity = giCreateOrderOpportunity();

        $proforma = (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $standard = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        expect($proforma->id)->not->toBe($standard->id)
            ->and(Invoice::query()->where('opportunity_id', $opportunity->id)->count())->toBe(2);
    });

    it('is idempotent on double submit', function () {
        $opportunity = giCreateQuotationOpportunity();

        $first = (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        $second = (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
            'opportunity_id' => $opportunity->id,
        ]));

        expect($second->id)->toBe($first->id)
            ->and(Invoice::query()->where('opportunity_id', $opportunity->id)->where('type', 'proforma')->count())->toBe(1);
    });
});
