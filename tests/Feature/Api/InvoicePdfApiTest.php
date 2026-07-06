<?php

use App\Actions\Documents\GenerateBillingIssueDocument;
use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Enums\PricingRoundingDirection;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

/**
 * @return array{invoice: Invoice}
 */
function invoicePdfApiFixture(): array
{
    Queue::fake();

    $opportunity = giCreateOrderOpportunity();
    app(DocumentTemplateSeeder::class)->seedForStore($opportunity->store);

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    return ['invoice' => $invoice->refresh()];
}

it('redirects to a signed PDF URL when the invoice document exists', function () {
    $user = User::factory()->owner()->create();
    Sanctum::actingAs($user, ['invoices:read']);

    ['invoice' => $invoice] = invoicePdfApiFixture();

    app(GenerateBillingIssueDocument::class)->forInvoice($invoice);
    $document = Document::query()->where('documentable_id', $invoice->id)->firstOrFail();

    $response = $this->getJson("/api/v1/invoices/{$invoice->id}/pdf");

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/documents/'.$document->id.'/file');
});

it('serves invoice pdf downloads with a friendly filename on local disk', function () {
    $user = User::factory()->owner()->create();
    Sanctum::actingAs($user, ['invoices:read']);

    ['invoice' => $invoice] = invoicePdfApiFixture();

    $documentData = app(GenerateBillingIssueDocument::class)->forInvoice($invoice);
    $document = Document::query()->findOrFail($documentData->id);

    $response = $this->getJson("/api/v1/invoices/{$invoice->id}/pdf");
    $response->assertRedirect();

    $this->get($response->headers->get('Location'))
        ->assertOk()
        ->assertHeader(
            'Content-Disposition',
            'inline; filename="'.$document->document_number.'-invoice.pdf"',
        );
});

it('returns 202 while the invoice PDF is still generating', function () {
    $user = User::factory()->owner()->create();
    Sanctum::actingAs($user, ['invoices:read']);

    ['invoice' => $invoice] = invoicePdfApiFixture();

    $response = $this->getJson("/api/v1/invoices/{$invoice->id}/pdf");

    $response->assertAccepted()
        ->assertJsonPath('status', 'queued');
});

it('forbids callers without invoices:read ability', function () {
    $user = User::factory()->owner()->create();
    Sanctum::actingAs($user, ['documents:read']);

    ['invoice' => $invoice] = invoicePdfApiFixture();

    $this->getJson("/api/v1/invoices/{$invoice->id}/pdf")->assertForbidden();
});

it('returns 404 for cross-store invoice access', function () {
    $owner = User::factory()->owner()->create();
    $this->actingAs($owner);
    ['invoice' => $invoice] = invoicePdfApiFixture();

    $member = Member::factory()->create();
    $restrictedUser = User::factory()->create(['member_id' => $member->id, 'is_owner' => false, 'is_active' => true]);
    $restrictedUser->givePermissionTo('invoices.view');
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => Store::factory()->create()->id,
        'created_at' => now(),
    ]);
    Sanctum::actingAs($restrictedUser, ['invoices:read']);

    $this->getJson("/api/v1/invoices/{$invoice->id}/pdf")->assertNotFound();
});

it('allows public sharing via document public token after generation', function () {
    $user = User::factory()->owner()->create();
    Sanctum::actingAs($user, ['invoices:read', 'documents:write']);

    ['invoice' => $invoice] = invoicePdfApiFixture();
    $document = Document::query()->where('documentable_id', $invoice->id)->first();

    if ($document === null) {
        $documentData = app(GenerateBillingIssueDocument::class)->forInvoice($invoice);
        $document = Document::query()->findOrFail($documentData->id);
    }

    $this->postJson("/api/v1/documents/{$document->id}/public_link")
        ->assertSuccessful();

    $document->refresh();

    expect($document->public_token)->not->toBeNull();

    $this->get('/d/'.$document->public_token)->assertSuccessful();
});
