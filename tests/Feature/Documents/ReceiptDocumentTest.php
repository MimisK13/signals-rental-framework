<?php

use App\Actions\Documents\GenerateDocument;
use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Data\Documents\GenerateDocumentData;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\RecordPaymentData;
use App\Enums\DocumentOutputFormat;
use App\Enums\PaymentMethod;
use App\Enums\PricingRoundingDirection;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\SystemDocumentTemplates;
use Carbon\CarbonImmutable;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    Queue::fake();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T12:00:00Z'));
    settings()->set('company.date_format_php', 'd/m/Y');
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{invoice: Invoice, payment: InvoicePayment, store_id: int}
 */
function receiptDocumentFixture(): array
{
    $opportunity = giCreateOrderOpportunity();
    $store = $opportunity->store;

    app(DocumentTemplateSeeder::class)->seedForStore($store);

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    (new RecordPayment)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => intdiv($invoice->total, 2),
            'payment_method' => PaymentMethod::BankTransfer->value,
            'reference' => 'RCT-PAY-1',
        ]),
    );

    $payment = InvoicePayment::query()->where('invoice_id', $invoice->id)->sole();

    return [
        'invoice' => $invoice->refresh(),
        'payment' => $payment,
        'store_id' => $store->id,
    ];
}

it('registers the receipt document type and resolver', function () {
    $registry = app(DocumentResolverRegistry::class);

    expect($registry->has('receipt'))->toBeTrue();

    $data = $registry->resolve('receipt', receiptDocumentFixture()['payment']->id);

    expect($data)->toHaveKeys(['payment', 'invoice', 'member', 'store'])
        ->and($data['payment']['reference'])->toBe('RCT-PAY-1')
        ->and($data['payment']['amount'])->toBeString();
});

it('ships a system receipt template definition', function () {
    $definition = collect(SystemDocumentTemplates::definitions())
        ->firstWhere('document_type', 'receipt');

    expect($definition)->not->toBeNull()
        ->and($definition['slug'])->toBe('system-receipt');
});

it('generates a receipt document from a payment through the standard pipeline', function () {
    ['payment' => $payment, 'store_id' => $storeId] = receiptDocumentFixture();

    $template = DocumentTemplate::query()
        ->where('store_id', $storeId)
        ->where('document_type', 'receipt')
        ->where('is_default', true)
        ->firstOrFail();

    $document = app(GenerateDocument::class)(GenerateDocumentData::from([
        'document_template_id' => $template->id,
        'documentable_id' => $payment->id,
        'documentable_type' => 'payment',
        'output_format' => DocumentOutputFormat::HtmlDownload,
    ]));

    expect($document->document_type)->toBe('receipt')
        ->and(Document::query()->whereKey($document->id)->exists())->toBeTrue()
        ->and($document->rendered_data)->toHaveKeys(['payment', 'invoice', 'member', 'store']);
});
