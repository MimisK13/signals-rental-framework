<?php

use App\Domain\Documents\Actions\GenerateBillingIssueDocument;
use App\Domain\Documents\Contracts\DocumentRenderer;
use App\Domain\Documents\Database\Seeders\DocumentTemplateSeeder;
use App\Domain\Documents\Enums\DocumentOutputFormat;
use App\Domain\Documents\Jobs\GenerateBillingIssueDocumentJob;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentSequence;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\DocumentResolverRegistry;
use App\Domain\Documents\Services\SystemDocumentTemplates;
use App\Domain\Invoices\Actions\GenerateInvoiceFromOpportunity;
use App\Domain\Invoices\Actions\IssueCreditNote;
use App\Domain\Invoices\Actions\IssueInvoice;
use App\Domain\Invoices\Data\GenerateInvoiceFromOpportunityData;
use App\Domain\Invoices\Data\IssueCreditNoteData;
use App\Domain\Invoices\Models\CreditNote;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Models\InvoiceTax;
use App\Domain\Tax\Enums\TaxBuyerType;
use App\Domain\Tax\Enums\TaxResultType;
use App\Enums\PricingRoundingDirection;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    Storage::fake('public');
    settings()->set('company.date_format_php', 'd/m/Y');
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    Queue::fake();
});

/**
 * @return array{invoice: Invoice, store_id: int}
 */
function invoiceDocumentFixture(): array
{
    $opportunity = giCreateOrderOpportunity();
    $store = $opportunity->store;

    app(DocumentTemplateSeeder::class)->seedForStore($store);

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->with(['items', 'taxes'])->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    return [
        'invoice' => $invoice->refresh()->load(['items', 'taxes']),
        'store_id' => $store->id,
    ];
}

it('registers invoice and credit note document resolvers', function () {
    $registry = app(DocumentResolverRegistry::class);

    expect($registry->has('invoice'))->toBeTrue()
        ->and($registry->has('credit_note'))->toBeTrue();
});

it('resolves issued invoice merge data with decimal string money', function () {
    ['invoice' => $invoice] = invoiceDocumentFixture();

    $data = app(DocumentResolverRegistry::class)->resolve('invoice', $invoice->id);

    expect($data)->toHaveKeys(['invoice', 'lines', 'tax_summary', 'payments', 'member', 'store'])
        ->and($data['invoice']['number'])->toBe($invoice->number)
        ->and($data['invoice']['total'])->toBeString()
        ->and($data['lines'])->not->toBeEmpty();
});

it('projects tax metadata on invoice tax rows for document rendering', function () {
    ['invoice' => $invoice] = invoiceDocumentFixture();

    $taxRow = InvoiceTax::query()->where('invoice_id', $invoice->id)->first();

    expect($taxRow)->not->toBeNull()
        ->and($taxRow->rate_percentage)->not->toBeNull()
        ->and($taxRow->tax_rate_name)->not->toBeNull()
        ->and($taxRow->result_type)->toBe(TaxResultType::Standard->value);
});

it('renders reverse charge legal text from projected tax metadata', function () {
    $zones = giWireZoneTaxInfrastructure();
    $opportunity = giCreateOrderOpportunity([
        'tax_zone_id' => $zones['euMember']->id,
        'buyer_type' => TaxBuyerType::Business->value,
        'tax_number' => 'DE123456789',
        'tax_number_validated' => true,
    ], [], $zones);

    app(DocumentTemplateSeeder::class)->seedForStore($opportunity->store);

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->with('taxes')->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    $taxRow = InvoiceTax::query()->where('invoice_id', $invoice->id)->firstOrFail();

    expect($taxRow->result_type)->toBe(TaxResultType::ReverseCharge->value)
        ->and($taxRow->legal_text)->not->toBeNull();

    $data = app(DocumentResolverRegistry::class)->resolve('invoice', $invoice->id);

    expect($data['tax_summary']['groups'][0]['legal_text'] ?? null)->not->toBeNull()
        ->and($data['tax_summary']['groups'][0]['result_type'] ?? null)->toBe('Reverse charge');
});

it('ships live invoice and credit note system template definitions', function () {
    $invoiceDefinition = collect(SystemDocumentTemplates::definitions())
        ->firstWhere('document_type', 'invoice');
    $creditNoteDefinition = collect(SystemDocumentTemplates::definitions())
        ->firstWhere('document_type', 'credit_note');

    expect($invoiceDefinition['body_html'])->toContain('@foreach ($lines as $line)')
        ->and($invoiceDefinition['body_html'])->toContain('tax_summary->groups')
        ->and($creditNoteDefinition['body_html'])->toContain('$invoice->number');
});

it('generates an invoice PDF through the billing issue document action', function () {
    ['invoice' => $invoice, 'store_id' => $storeId] = invoiceDocumentFixture();

    $template = DocumentTemplate::query()
        ->where('store_id', $storeId)
        ->where('document_type', 'invoice')
        ->where('is_default', true)
        ->firstOrFail();

    $document = app(GenerateBillingIssueDocument::class)->forInvoice($invoice);

    expect($document->document_type)->toBe('invoice')
        ->and($document->document_number)->toBe($invoice->number)
        ->and(Document::query()->whereKey($document->id)->exists())->toBeTrue()
        ->and($document->rendered_data)->toHaveKeys(['invoice', 'lines', 'tax_summary', 'member', 'store'])
        ->and($document->download_url)->not->toBeNull();

    $regenerated = app(GenerateBillingIssueDocument::class)->forInvoice($invoice->refresh());

    expect($regenerated->id)->toBe($document->id);
});

it('generates a credit note PDF with original invoice reference', function () {
    ['invoice' => $invoice] = invoiceDocumentFixture();

    $creditNoteData = (new IssueCreditNote)(
        $invoice->load('items'),
        IssueCreditNoteData::from([
            'lines' => [[
                'invoice_item_id' => $invoice->items->first()->id,
                'quantity' => '0.50',
            ]],
        ]),
    );

    $creditNote = CreditNote::query()->findOrFail($creditNoteData->id);
    $document = app(GenerateBillingIssueDocument::class)->forCreditNote($creditNote);

    expect($document->document_type)->toBe('credit_note')
        ->and($document->document_number)->toBe($creditNote->number)
        ->and($document->rendered_data['invoice']['number'] ?? null)->toBe($invoice->number)
        ->and($document->rendered_data['credit_note']['total'] ?? null)->toBeString();
});

it('does not advance the credit note entity sequence when generating its PDF', function () {
    ['invoice' => $invoice] = invoiceDocumentFixture();

    $creditNoteData = (new IssueCreditNote)(
        $invoice->load('items'),
        IssueCreditNoteData::from([
            'lines' => [[
                'invoice_item_id' => $invoice->items->first()->id,
                'quantity' => '0.50',
            ]],
        ]),
    );

    $creditNote = CreditNote::query()->findOrFail($creditNoteData->id);
    $sequenceBefore = DocumentSequence::query()
        ->where('store_id', $invoice->store_id)
        ->where('document_type', 'credit_note')
        ->value('next_number');

    app(GenerateBillingIssueDocument::class)->forCreditNote($creditNote);

    $sequenceAfter = DocumentSequence::query()
        ->where('store_id', $invoice->store_id)
        ->where('document_type', 'credit_note')
        ->value('next_number');

    expect($sequenceAfter)->toBe($sequenceBefore);
});

it('dispatches a queued billing document job when an invoice is issued', function () {
    ['invoice' => $invoice] = invoiceDocumentFixture();

    Queue::assertPushed(GenerateBillingIssueDocumentJob::class, function (GenerateBillingIssueDocumentJob $job) use ($invoice): bool {
        return $job->sourceType === 'invoice'
            && $job->sourceId === $invoice->id;
    });
});

it('skips the billing document job when the store has no active invoice template', function () {
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    Queue::assertNotPushed(GenerateBillingIssueDocumentJob::class);
    expect(Cache::get(IssueInvoice::pdfStatusCacheKey((int) $invoice->id)))->toBeNull();
});

it('renders invoice template preview HTML containing line descriptions', function () {
    ['invoice' => $invoice, 'store_id' => $storeId] = invoiceDocumentFixture();

    $template = DocumentTemplate::query()
        ->where('store_id', $storeId)
        ->where('document_type', 'invoice')
        ->firstOrFail();

    $preview = app(DocumentRenderer::class)->render(
        $template->fresh('store'),
        'invoice',
        $invoice->id,
        DocumentOutputFormat::Preview,
    );

    expect($preview->success)->toBeTrue()
        ->and($preview->html)->toContain($invoice->items->first()->description);
});
