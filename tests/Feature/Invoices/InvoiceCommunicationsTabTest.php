<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Contracts\Documents\DocumentRenderer;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Enums\DocumentOutputFormat;
use App\Models\CommunicationLog;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Notifications\CommunicationLogQuery;
use App\ValueObjects\Documents\DocumentRenderResult;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(NotificationTypeSeeder::class);
    Mail::fake();
    settings()->set('invoices.auto_email_on_issue', true);

    $this->mock(DocumentRenderer::class, function ($mock): void {
        $mock->shouldReceive('render')->andReturn(DocumentRenderResult::success(
            html: '<html></html>',
            renderedData: ['frozen' => true],
            format: DocumentOutputFormat::Pdf,
            pdfBinary: '%PDF-1.4-test-binary',
        ));
        $mock->shouldReceive('renderFromSnapshot')->andReturn(DocumentRenderResult::success(
            html: '<html></html>',
            renderedData: ['frozen' => true],
            format: DocumentOutputFormat::Pdf,
            pdfBinary: '%PDF-1.4-test-binary',
        ));
    });
});

it('lists invoice billing communications on the communications tab', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $opportunity = giCreateOrderOpportunity();
    $member = $opportunity->member;
    $member->emails()->create(['address' => 'billing@example.com', 'is_primary' => true]);

    app(DocumentTemplateSeeder::class)->seedForStore($opportunity->store);
    app(CommunicationTemplateSeeder::class)->seedForStore($opportunity->store);

    DocumentSequence::factory()->create([
        'store_id' => $opportunity->store_id,
        'document_type' => 'invoice',
        'prefix' => 'INV-',
        'padding' => 4,
        'next_number' => 1,
    ]);

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);

    (new IssueInvoice)($invoice->refresh());

    $log = CommunicationLog::query()
        ->where('notification_type_key', 'invoice.created')
        ->sole();

    expect($log->attachments)->not->toBeNull()
        ->and(app(CommunicationLogQuery::class)->forInvoice($invoice->refresh(), $user)->count())->toBe(1);

    Volt::test('invoices.communications', ['invoice' => $invoice])
        ->assertSee('invoice.created')
        ->assertSee($member->name);
});

it('gates the invoice communications tab behind invoice view permission', function () {
    $user = User::factory()->create(['is_owner' => false, 'is_active' => true]);
    $this->actingAs($user);

    $invoice = Invoice::factory()->create();

    Volt::test('invoices.communications', ['invoice' => $invoice])
        ->assertForbidden();
});
