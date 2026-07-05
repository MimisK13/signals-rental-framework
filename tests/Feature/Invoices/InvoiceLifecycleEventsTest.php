<?php

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\VoidInvoice;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Jobs\DeliverWebhook;
use App\Models\ActionLog;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use App\Models\Webhook;
use Carbon\CarbonImmutable;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(NotificationTypeSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T12:00:00Z'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('fires invoice.created audit when a draft is generated', function () {
    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));

    assertActionLogged('invoice.created', Invoice::class, $draft->id, auth()->id());
});

it('fires invoice.issued audit and webhook when an invoice is issued', function () {
    Queue::fake();

    Webhook::factory()->create(['events' => ['invoice.issued'], 'is_active' => true]);

    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));

    (new IssueInvoice)(Invoice::query()->findOrFail($draft->id));

    assertActionLogged('invoice.issued', Invoice::class, $draft->id, auth()->id());

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'invoice.issued');
});

it('fires invoice.overdue audit and webhook from the scheduler command', function () {
    Queue::fake();

    Webhook::factory()->create(['events' => ['invoice.overdue'], 'is_active' => true]);

    $invoice = Invoice::factory()->create([
        'status' => 'issued',
        'amount_outstanding' => 5000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    $this->artisan('invoices:process-overdue')->assertSuccessful();

    assertActionLogged('invoice.overdue', Invoice::class, $invoice->id);

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'invoice.overdue');
});

it('does not re-notify overdue invoices on subsequent scheduler runs', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'issued',
        'amount_outstanding' => 5000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    $this->artisan('invoices:process-overdue')->assertSuccessful();
    $this->artisan('invoices:process-overdue')->assertSuccessful();

    expect(ActionLog::query()->where('action', 'invoice.overdue')->where('auditable_id', $invoice->id)->count())->toBe(1)
        ->and($invoice->fresh()->overdue_notified_at)->not->toBeNull();
});

it('fires invoice.voided audit and webhook when an invoice is voided', function () {
    Queue::fake();

    Webhook::factory()->create(['events' => ['invoice.voided'], 'is_active' => true]);

    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);

    (new IssueInvoice)($invoice);
    (new VoidInvoice)($invoice->refresh());

    assertActionLogged('invoice.voided', Invoice::class, $invoice->id, auth()->id());

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'invoice.voided');
});
