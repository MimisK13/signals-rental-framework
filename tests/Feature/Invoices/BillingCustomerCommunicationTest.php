<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\SendMemberStatement;
use App\Contracts\Documents\DocumentRenderer;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Data\Invoices\RecordPaymentData;
use App\Data\Invoices\SendMemberStatementData;
use App\Enums\DocumentOutputFormat;
use App\Enums\PaymentMethod;
use App\Enums\PricingRoundingDirection;
use App\Jobs\SendBillingCustomerCommunicationJob;
use App\Models\CommunicationLog;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\User;
use App\ValueObjects\Documents\DocumentRenderResult;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(NotificationTypeSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    Storage::fake('public');
    settings()->set('company.name', 'Acme Rentals');
    settings()->set('company.base_currency', 'GBP');
    settings()->set('company.date_format_php', 'd/m/Y');
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('invoices.auto_email_on_issue', true);
    settings()->set('invoices.auto_email_on_credit_note', true);
    settings()->set('invoices.auto_email_on_payment', true);

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

/**
 * @return array{invoice: Invoice, member: Member, store_id: int}
 */
function billingCommunicationFixture(): array
{
    $opportunity = giCreateOrderOpportunity();
    $member = $opportunity->member;
    $member->emails()->create(['address' => 'billing@example.com', 'is_primary' => true]);

    app(DocumentTemplateSeeder::class)->seedForStore($opportunity->store);
    app(CommunicationTemplateSeeder::class)->seedForStore($opportunity->store);

    foreach (['invoice', 'credit_note', 'receipt', 'statement'] as $documentType) {
        DocumentSequence::factory()->create([
            'store_id' => $opportunity->store_id,
            'document_type' => $documentType,
            'prefix' => strtoupper(substr(str_replace('_', '', $documentType), 0, 3)).'-',
            'padding' => 4,
            'next_number' => 1,
        ]);
    }

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);

    return [
        'invoice' => $invoice,
        'member' => $member->fresh(),
        'store_id' => $opportunity->store_id,
    ];
}

describe('invoice issue customer email', function () {
    it('emails the customer with a PDF attachment and logs delivery', function () {
        Mail::fake();

        ['invoice' => $invoice, 'member' => $member] = billingCommunicationFixture();

        (new IssueInvoice)($invoice);

        $log = CommunicationLog::query()
            ->where('notification_type_key', 'invoice.created')
            ->where('notifiable_id', $member->id)
            ->sole();

        expect($log->channel)->toBe('email')
            ->and($log->subject_type)->toBe(Member::class)
            ->and($log->subject_id)->toBe($member->id)
            ->and($log->recipient_address)->toBe('billing@example.com')
            ->and($log->status)->toBe('sent')
            ->and($log->attachments)->toHaveCount(1);
    });

    it('does not email when auto_email_on_issue is disabled', function () {
        Mail::fake();
        settings()->set('invoices.auto_email_on_issue', false);

        ['invoice' => $invoice] = billingCommunicationFixture();

        (new IssueInvoice)($invoice);

        Mail::assertNothingSent();
        expect(CommunicationLog::query()->where('notification_type_key', 'invoice.created')->count())->toBe(0);
    });
});

describe('payment receipt customer email', function () {
    it('emails a receipt when a payment is recorded', function () {
        Mail::fake();

        ['invoice' => $invoice, 'member' => $member] = billingCommunicationFixture();
        (new IssueInvoice)($invoice->refresh());

        (new RecordPayment)(
            $invoice->refresh(),
            RecordPaymentData::from([
                'amount' => intdiv($invoice->total, 2),
                'payment_method' => PaymentMethod::BankTransfer->value,
                'reference' => 'PAY-001',
            ]),
        );

        $log = CommunicationLog::query()
            ->where('notification_type_key', 'invoice.payment_received')
            ->sole();

        expect($log->notifiable_id)->toBe($member->id)
            ->and($log->status)->toBe('sent');
    });
});

describe('credit note customer email', function () {
    it('emails the customer when a credit note is issued', function () {
        Mail::fake();

        ['invoice' => $invoice, 'member' => $member] = billingCommunicationFixture();
        (new IssueInvoice)($invoice->refresh());

        (new IssueCreditNote)(
            $invoice->refresh(),
            IssueCreditNoteData::from(['lines' => null]),
        );

        $log = CommunicationLog::query()
            ->where('notification_type_key', 'invoice.credit_note')
            ->sole();

        expect($log->notifiable_id)->toBe($member->id)
            ->and($log->status)->toBe('sent');
    });
});

describe('member statement email', function () {
    it('generates and emails a statement document', function () {
        Mail::fake();

        ['invoice' => $invoice, 'member' => $member, 'store_id' => $storeId] = billingCommunicationFixture();
        (new IssueInvoice)($invoice->refresh());

        app(SendMemberStatement::class)($member, SendMemberStatementData::from([
            'member_id' => $member->id,
            'store_id' => $storeId,
        ]));

        $log = CommunicationLog::query()
            ->where('notification_type_key', 'statement.emailed')
            ->sole();

        expect($log->notifiable_id)->toBe($member->id)
            ->and($log->status)->toBe('sent');
    });

    it('exposes a statement email API endpoint', function () {
        Mail::fake();
        $user = User::factory()->owner()->create();
        Sanctum::actingAs($user, ['*']);
        $this->actingAs($user);

        ['invoice' => $invoice, 'member' => $member, 'store_id' => $storeId] = billingCommunicationFixture();
        (new IssueInvoice)($invoice->refresh());

        $this->postJson("/api/v1/members/{$member->id}/statement/email", [
            'store_id' => $storeId,
        ])->assertAccepted();

        expect(CommunicationLog::query()->where('notification_type_key', 'statement.emailed')->exists())->toBeTrue();
    });
});

describe('opt-out suppression', function () {
    it('suppresses billing emails for opted-out members but still logs suppressed status', function () {
        Mail::fake();

        ['invoice' => $invoice, 'member' => $member] = billingCommunicationFixture();
        $member->update([
            'communication_preferences' => [
                'opt_out' => ['invoice.created'],
            ],
        ]);

        (new IssueInvoice)($invoice->refresh());

        Mail::assertNothingSent();

        $log = CommunicationLog::query()
            ->where('notification_type_key', 'invoice.created')
            ->sole();

        expect($log->status)->toBe('suppressed')
            ->and($log->failure_reason)->toContain('opted out');
    });
});

describe('replay safety', function () {
    it('does not duplicate billing emails or communication logs on replay', function () {
        Mail::fake();

        ['invoice' => $invoice] = billingCommunicationFixture();

        (new IssueInvoice)($invoice->refresh());

        (new RecordPayment)(
            $invoice->refresh(),
            RecordPaymentData::from([
                'amount' => intdiv($invoice->total, 2),
                'payment_method' => PaymentMethod::BankTransfer->value,
            ]),
        );

        $logsBefore = CommunicationLog::query()->count();
        $eventCountBeforeReplay = verbEventCount();

        InvoicePayment::query()->delete();
        Invoice::query()->forceDelete();

        Verbs::replay();

        expect(verbEventCount())->toBe($eventCountBeforeReplay)
            ->and(CommunicationLog::query()->count())->toBe($logsBefore);
    });
});

describe('job dispatch', function () {
    it('queues billing communication delivery on the communications queue', function () {
        Queue::fake();

        ['invoice' => $invoice] = billingCommunicationFixture();

        (new IssueInvoice)($invoice->refresh());

        Queue::assertPushed(SendBillingCustomerCommunicationJob::class, function (SendBillingCustomerCommunicationJob $job): bool {
            return $job->queue === 'communications';
        });
    });
});
