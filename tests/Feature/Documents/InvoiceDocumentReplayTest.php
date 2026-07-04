<?php

use App\Actions\Documents\GenerateBillingIssueDocument;
use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Enums\PricingRoundingDirection;
use App\Jobs\GenerateBillingIssueDocumentJob;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTax;
use App\Models\User;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    Storage::fake('public');
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

it('does not duplicate invoice PDF documents or append events during replay', function () {
    Queue::fake();

    $opportunity = giCreateOrderOpportunity();
    app(DocumentTemplateSeeder::class)->seedForStore($opportunity->store);

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    app(GenerateBillingIssueDocument::class)->forInvoice($invoice->refresh());

    $documentsBefore = Document::query()->orderBy('id')->get()->map(
        fn (Document $row): array => $row->getAttributes(),
    )->all();

    $eventCountBeforeReplay = verbEventCount();
    $documentCountBefore = Document::query()->count();

    InvoiceTax::query()->delete();
    InvoiceItem::query()->delete();
    Invoice::query()->forceDelete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay)
        ->and(Document::query()->count())->toBe($documentCountBefore);

    $documentsAfter = Document::query()->orderBy('id')->get()->map(
        fn (Document $row): array => $row->getAttributes(),
    )->all();

    expect($documentsAfter)->toBe($documentsBefore);
});

it('skips billing document job execution while verbs is replaying', function () {
    Queue::fake();

    $opportunity = giCreateOrderOpportunity();
    app(DocumentTemplateSeeder::class)->seedForStore($opportunity->store);

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);

    $job = new GenerateBillingIssueDocumentJob(
        jobId: (string) Str::uuid(),
        sourceType: 'invoice',
        sourceId: (int) $invoice->id,
        userId: auth()->id(),
    );

    Verbs::shouldReceive('isReplaying')->andReturnTrue();

    $job->handle(app(GenerateBillingIssueDocument::class));

    expect(Document::query()->count())->toBe(0);
});
