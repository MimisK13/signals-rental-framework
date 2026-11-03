<?php

use App\Actions\Stores\CreateStore;
use App\Data\Stores\CreateStoreData;
use App\Domain\Documents\Contracts\DocumentRenderer;
use App\Domain\Documents\Database\Seeders\DocumentTemplateSeeder;
use App\Domain\Documents\Enums\DocumentOutputFormat;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\SystemDocumentTemplates;
use App\Domain\Invoices\Actions\GenerateDepositInvoice;
use App\Domain\Invoices\Actions\GenerateInvoiceFromOpportunity;
use App\Domain\Invoices\Actions\GenerateProformaInvoice;
use App\Domain\Invoices\Actions\IssueCreditNote;
use App\Domain\Invoices\Actions\IssueInvoice;
use App\Domain\Invoices\Actions\RecordPayment;
use App\Domain\Invoices\Data\GenerateDepositInvoiceData;
use App\Domain\Invoices\Data\GenerateInvoiceFromOpportunityData;
use App\Domain\Invoices\Data\GenerateProformaInvoiceData;
use App\Domain\Invoices\Data\IssueCreditNoteData;
use App\Domain\Invoices\Data\RecordPaymentData;
use App\Domain\Invoices\Enums\PaymentMethod;
use App\Domain\Invoices\Models\CreditNote;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Models\InvoicePayment;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\ConvertToOrder;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Services\OpportunityTotalsCalculator;
use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxRule;
use App\Enums\PricingRoundingDirection;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
    settings()->set('invoices.due_days', 30);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T12:00:00Z'));
    Queue::fake();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{
 *     store: Store,
 *     member: Member,
 *     opportunity: Opportunity,
 *     payment: InvoicePayment,
 *     issued_invoice: Invoice,
 *     deposit_invoice: Invoice,
 *     proforma_invoice: Invoice,
 *     credit_note: CreditNote,
 *     purchase_order: PurchaseOrder
 * }
 */
function seededTemplateRenderFixture(): array
{
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    $store = Store::factory()->create([
        'timezone' => 'Europe/London',
        'brand_primary_colour' => '#003366',
        'brand_secondary_colour' => '#666666',
        'brand_font_family' => 'Helvetica',
    ]);

    app(DocumentTemplateSeeder::class)->seedForStore($store);

    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $orgClass->id,
    ]);

    $product = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);

    $opportunity = Opportunity::factory()->create([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'subject' => 'Seeded Template Render Test',
        'currency_code' => 'GBP',
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-03T17:00:00Z'),
        'charge_total' => 10_000,
        'tax_total' => 2_000,
    ]);

    OpportunityItem::factory()->sale()->create([
        'opportunity_id' => $opportunity->id,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'name' => 'Render Line',
        'quantity' => '1',
        'unit_price' => 10_000,
        'total' => 10_000,
    ]);

    (new ConvertToQuotation)($opportunity->refresh());
    (new ConvertToOrder)($opportunity->refresh());

    app(OpportunityTotalsCalculator::class)->rollUp(
        $opportunity->fresh(['items.itemSurcharges', 'costs', 'member', 'store']),
    );

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    (new RecordPayment)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => max(1, intdiv($invoice->total, 2)),
            'payment_method' => PaymentMethod::BankTransfer->value,
            'reference' => 'SEED-RENDER-001',
        ]),
    );

    $payment = InvoicePayment::query()->where('invoice_id', $invoice->id)->sole();

    $depositDraft = (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
        'opportunity_id' => $opportunity->id,
        'amount_percentage' => '50',
    ]));
    $depositInvoice = Invoice::query()->findOrFail($depositDraft->id);
    (new IssueInvoice)($depositInvoice);

    $proformaDraft = (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $proformaInvoice = Invoice::query()->findOrFail($proformaDraft->id);
    (new IssueInvoice)($proformaInvoice);

    $creditNoteData = (new IssueCreditNote)(
        $invoice->refresh()->load('items'),
        IssueCreditNoteData::from([
            'lines' => [[
                'invoice_item_id' => $invoice->items->first()->id,
                'quantity' => '0.50',
            ]],
        ]),
    );
    $creditNote = CreditNote::query()->findOrFail($creditNoteData->id);

    $purchaseOrder = createTestPurchaseOrder([
        'store_id' => $store->id,
        'opportunity_id' => $opportunity->id,
    ]);

    return [
        'store' => $store,
        'member' => $member,
        'opportunity' => $opportunity,
        'payment' => $payment,
        'issued_invoice' => $invoice->refresh(),
        'deposit_invoice' => $depositInvoice->refresh(),
        'proforma_invoice' => $proformaInvoice->refresh(),
        'credit_note' => $creditNote,
        'purchase_order' => $purchaseOrder,
    ];
}

describe('DocumentTemplateSeeder', function () {
    it('seeds one system default template per core document type', function () {
        $store = Store::factory()->create();

        app(DocumentTemplateSeeder::class)->seedForStore($store);

        expect(DocumentTemplate::query()->where('store_id', $store->id)->count())->toBe(count(SystemDocumentTemplates::CORE_TYPES));

        foreach (SystemDocumentTemplates::CORE_TYPES as $type) {
            $template = DocumentTemplate::query()
                ->where('store_id', $store->id)
                ->where('document_type', $type)
                ->first();

            expect($template)->not->toBeNull()
                ->and($template->is_system)->toBeTrue()
                ->and($template->is_default)->toBeTrue()
                ->and($template->slug)->toBe('system-'.$type);
        }
    });

    it('is idempotent when seeding the same store twice', function () {
        $store = Store::factory()->create();
        $seeder = app(DocumentTemplateSeeder::class);

        $seeder->seedForStore($store);
        $seeder->seedForStore($store);

        expect(DocumentTemplate::query()->where('store_id', $store->id)->count())
            ->toBe(count(SystemDocumentTemplates::CORE_TYPES));
    });

    it('seeds templates when a store is created through CreateStore', function () {
        $store = (new CreateStore)(new CreateStoreData(
            name: 'North Depot',
            country_code: 'GB',
        ));

        expect(DocumentTemplate::query()->where('store_id', $store->id)->count())
            ->toBe(count(SystemDocumentTemplates::CORE_TYPES));
    });

    it('renders a valid non-empty PDF for every seeded system template type', function () {
        ['store' => $store, 'member' => $member, 'opportunity' => $opportunity, 'payment' => $payment, 'issued_invoice' => $issuedInvoice, 'deposit_invoice' => $depositInvoice, 'proforma_invoice' => $proformaInvoice, 'credit_note' => $creditNote, 'purchase_order' => $purchaseOrder] = seededTemplateRenderFixture();

        foreach (SystemDocumentTemplates::CORE_TYPES as $documentType) {
            $template = DocumentTemplate::query()
                ->where('store_id', $store->id)
                ->where('document_type', $documentType)
                ->firstOrFail();

            $sourceId = match ($documentType) {
                'statement' => $member->id,
                'receipt' => $payment->id,
                'invoice' => $issuedInvoice->id,
                'credit_note' => $creditNote->id,
                'pro_forma' => $proformaInvoice->id,
                'deposit' => $depositInvoice->id,
                'purchase_order' => $purchaseOrder->id,
                default => $opportunity->id,
            };

            $resolverType = match ($documentType) {
                'invoice', 'pro_forma', 'deposit' => 'invoice',
                'credit_note' => 'credit_note',
                'statement' => 'statement',
                'receipt' => 'receipt',
                'purchase_order' => 'purchase_order',
                'return_list' => 'return_list',
                default => 'opportunity',
            };

            $result = app(DocumentRenderer::class)->render(
                $template->fresh('store'),
                $resolverType,
                $sourceId,
                DocumentOutputFormat::Pdf,
            );

            expect($result->success)->toBeTrue("Expected {$documentType} template to render successfully")
                ->and($result->pdfBinary)->not->toBeEmpty()
                ->and($result->pdfBinary)->toStartWith('%PDF');
        }
    });

    it('renders a valid non-empty PDF for every core financial document type', function () {
        ['store' => $store, 'member' => $member, 'payment' => $payment, 'issued_invoice' => $issuedInvoice, 'deposit_invoice' => $depositInvoice, 'proforma_invoice' => $proformaInvoice, 'credit_note' => $creditNote, 'purchase_order' => $purchaseOrder] = seededTemplateRenderFixture();

        foreach (SystemDocumentTemplates::CORE_FINANCIAL_TYPES as $documentType) {
            $template = DocumentTemplate::query()
                ->where('store_id', $store->id)
                ->where('document_type', $documentType)
                ->firstOrFail();

            $sourceId = match ($documentType) {
                'statement' => $member->id,
                'receipt' => $payment->id,
                'invoice' => $issuedInvoice->id,
                'credit_note' => $creditNote->id,
                'pro_forma' => $proformaInvoice->id,
                'deposit' => $depositInvoice->id,
                'purchase_order' => $purchaseOrder->id,
            };

            $resolverType = match ($documentType) {
                'invoice', 'pro_forma', 'deposit' => 'invoice',
                'credit_note' => 'credit_note',
                'statement' => 'statement',
                'receipt' => 'receipt',
                'purchase_order' => 'purchase_order',
            };

            $result = app(DocumentRenderer::class)->render(
                $template->fresh('store'),
                $resolverType,
                $sourceId,
                DocumentOutputFormat::Pdf,
            );

            expect($result->success)->toBeTrue("Expected {$documentType} financial template to render successfully")
                ->and($result->pdfBinary)->not->toBeEmpty()
                ->and($result->pdfBinary)->toStartWith('%PDF');
        }
    });

    it('includes rendered line item content in seeded commercial PDFs', function () {
        ['store' => $store, 'opportunity' => $opportunity] = seededTemplateRenderFixture();

        $template = DocumentTemplate::query()
            ->where('store_id', $store->id)
            ->where('document_type', 'quote')
            ->firstOrFail();

        $preview = app(DocumentRenderer::class)->render(
            $template->fresh('store'),
            'opportunity',
            $opportunity->id,
            DocumentOutputFormat::Preview,
        );

        expect($preview->success)->toBeTrue()
            ->and($preview->html)->toContain('Render Line');
    });
});
