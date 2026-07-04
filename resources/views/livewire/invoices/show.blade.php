<?php

use App\Actions\Invoices\DeleteDraftInvoice;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Jobs\GenerateBillingIssueDocumentJob;
use App\Models\Document;
use App\Enums\DocumentStatus;
use App\Services\Documents\DocumentTemplateResolver;
use App\Services\FileService;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\VoidInvoice;
use App\Actions\Notifications\DispatchBillingCustomerCommunication;
use App\Data\Invoices\IssueCreditNoteData;
use App\Data\Invoices\RecordPaymentData;
use App\Enums\BillingCommunicationKind;
use App\Enums\PaymentMethod;
use App\Livewire\Concerns\HandlesVerbsStateErrors;
use App\Models\Invoice;
use App\Support\CostVisibility;
use App\Services\Invoices\InvoiceMarginCalculator;
use Brick\Money\Money;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use HandlesVerbsStateErrors;

    public Invoice $invoice;

    public bool $showPaymentModal = false;

    public bool $showCreditNoteModal = false;

    public string $paymentAmount = '';

    public string $paymentMethod = PaymentMethod::BankTransfer->value;

    public ?string $paymentReference = null;

    public ?string $creditNoteNotes = null;

    public bool $fullCreditNote = true;

    /** @var array<int, array{invoice_item_id: int, quantity: string}> */
    public array $creditNoteLines = [];

    public function mount(Invoice $invoice): void
    {
        Gate::authorize('view', $invoice);

        $this->invoice = $invoice->load([
            'member',
            'store',
            'opportunity.costs',
            'opportunity.items',
            'items.product',
            'items.opportunityItem',
            'taxes',
            'payments',
            'creditNotes.items',
        ]);
    }

    public function rendering(View $view): void
    {
        $view->title($this->invoice->number);
    }

    public function issue(): void
    {
        Queue::fake();

        $this->runVerbsTransition(
            fn () => (new IssueInvoice)($this->invoice),
            fn () => $this->refreshInvoice(),
        );
    }

    public function voidInvoice(): void
    {
        $this->runVerbsTransition(
            fn () => (new VoidInvoice)($this->invoice),
            fn () => $this->refreshInvoice(),
        );
    }

    public function deleteDraft(): void
    {
        $this->runVerbsTransition(
            fn () => (new DeleteDraftInvoice)($this->invoice),
            fn () => $this->redirect(route('invoices.index'), navigate: true),
        );
    }

    public function emailInvoice(): void
    {
        app(DispatchBillingCustomerCommunication::class)(
            BillingCommunicationKind::Invoice,
            (int) $this->invoice->id,
            (int) $this->invoice->store_id,
            explicit: true,
        );
    }

    public function downloadPdf()
    {
        $documentType = $this->invoice->typeEnum()->documentType();

        $document = Document::query()
            ->where('documentable_type', $this->invoice->getMorphClass())
            ->where('documentable_id', $this->invoice->id)
            ->where('document_type', $documentType)
            ->where('status', '!=', DocumentStatus::Void)
            ->whereNotNull('pdf_path')
            ->latest('id')
            ->first();

        if ($document !== null) {
            $url = app(FileService::class)->signedUrlOrNull($document->pdf_path);

            if ($url !== null) {
                return redirect()->away($url);
            }
        }

        $hasTemplate = app(DocumentTemplateResolver::class)->hasActiveTemplate(
            (int) $this->invoice->store_id,
            $documentType,
        );

        if (! $hasTemplate) {
            $this->dispatch('notify', message: 'Invoice PDF is not available yet. It may still be generating.');

            return;
        }

        $jobId = (string) Str::uuid();

        Cache::put(IssueInvoice::pdfStatusCacheKey((int) $this->invoice->id), [
            'status' => 'queued',
            'job_id' => $jobId,
        ], now()->addHours(1));

        GenerateBillingIssueDocumentJob::dispatch(
            $jobId,
            'invoice',
            (int) $this->invoice->id,
            auth()->id(),
        );

        $this->dispatch('notify', message: 'Generating — try again shortly.');
    }

    public function openPaymentModal(): void
    {
        $this->paymentAmount = $this->invoice->formatMoneyCost('amount_outstanding');
        $this->showPaymentModal = true;
    }

    public function recordPayment(): void
    {
        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethod' => ['required', 'string'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
        ]);

        $minor = Money::of($this->paymentAmount, $this->invoice->currency_code, roundingMode: RoundingMode::HALF_UP)
            ->getMinorAmount()
            ->toInt();

        $this->runVerbsTransition(function () use ($minor): void {
            (new RecordPayment)($this->invoice, RecordPaymentData::from([
                'amount' => $minor,
                'payment_method' => $this->paymentMethod,
                'reference' => $this->paymentReference,
            ]));

            $this->showPaymentModal = false;
            $this->refreshInvoice();
        });
    }

    public function openCreditNoteModal(): void
    {
        $this->fullCreditNote = true;
        $this->creditNoteNotes = null;
        $this->resetCreditNoteLines();
        $this->showCreditNoteModal = true;
    }

    public function updatedFullCreditNote(bool $fullCreditNote): void
    {
        if (! $fullCreditNote) {
            $this->resetCreditNoteLines();
        }
    }

    public function issueCreditNote(): void
    {
        $lines = null;

        if (! $this->fullCreditNote) {
            $this->validatePartialCreditNoteLines();

            $lines = collect($this->creditNoteLines)
                ->filter(fn (array $line): bool => bccomp($line['quantity'], '0', 2) > 0)
                ->map(fn (array $line): array => [
                    'invoice_item_id' => $line['invoice_item_id'],
                    'quantity' => $line['quantity'],
                ])
                ->values()
                ->all();
        }

        $this->runVerbsTransition(function () use ($lines): void {
            (new IssueCreditNote)($this->invoice, IssueCreditNoteData::from([
                'lines' => $lines,
                'notes' => $this->creditNoteNotes,
            ]));

            $this->showCreditNoteModal = false;
            $this->refreshInvoice();
        });
    }

    public function remainingCreditableQuantity(int $invoiceItemId): string
    {
        $item = $this->invoice->items->firstWhere('id', $invoiceItemId);

        if ($item === null) {
            return '0.00';
        }

        $credited = $this->creditedQuantitiesByInvoiceItem()[$invoiceItemId] ?? '0.00';
        $remaining = bcsub((string) $item->quantity, $credited, 2);

        return bccomp($remaining, '0', 2) < 0 ? '0.00' : $remaining;
    }

    private function validatePartialCreditNoteLines(): void
    {
        $rules = [];
        $messages = [];

        foreach ($this->creditNoteLines as $itemId => $line) {
            $remaining = $this->remainingCreditableQuantity((int) $itemId);
            $rules["creditNoteLines.{$itemId}.quantity"] = ['nullable', 'numeric', 'min:0', 'max:'.$remaining];
            $messages["creditNoteLines.{$itemId}.quantity.max"] = "Credit quantity cannot exceed the remaining creditable quantity ({$remaining}).";
        }

        $this->validate($rules, $messages);

        $selected = collect($this->creditNoteLines)
            ->filter(fn (array $line): bool => bccomp($line['quantity'], '0', 2) > 0);

        if ($selected->isEmpty()) {
            throw ValidationException::withMessages([
                'creditNoteLines' => ['At least one invoice line must have a credit quantity greater than zero.'],
            ]);
        }
    }

    private function resetCreditNoteLines(): void
    {
        $this->creditNoteLines = [];

        foreach ($this->invoice->items as $item) {
            $remaining = $this->remainingCreditableQuantity((int) $item->id);

            if (bccomp($remaining, '0', 2) <= 0) {
                continue;
            }

            $this->creditNoteLines[$item->id] = [
                'invoice_item_id' => $item->id,
                'quantity' => '0',
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    private function creditedQuantitiesByInvoiceItem(): array
    {
        $quantities = [];

        foreach ($this->invoice->creditNotes as $creditNote) {
            foreach ($creditNote->items as $creditNoteItem) {
                $itemId = (int) $creditNoteItem->invoice_item_id;
                $quantities[$itemId] = bcadd(
                    $quantities[$itemId] ?? '0',
                    (string) $creditNoteItem->quantity,
                    2,
                );
            }
        }

        return $quantities;
    }

    private function refreshInvoice(): void
    {
        $this->invoice->refresh()->load([
            'member',
            'store',
            'opportunity.costs',
            'opportunity.items',
            'items.product',
            'items.opportunityItem',
            'taxes',
            'payments',
            'creditNotes.items',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $effective = $this->invoice->effectiveStatus();

        return [
            'effectiveStatus' => $effective,
            'canIssue' => Gate::check('issue', $this->invoice),
            'canVoid' => Gate::check('void', $this->invoice),
            'canUpdate' => Gate::check('update', $this->invoice),
            'canDelete' => Gate::check('delete', $this->invoice) && $this->invoice->status === 'draft',
            'canPay' => Gate::check('recordPayment', $this->invoice),
            'canCredit' => Gate::check('issueCredit', $this->invoice),
            'paymentMethods' => PaymentMethod::cases(),
            'margin' => CostVisibility::canView()
                ? app(InvoiceMarginCalculator::class)->calculate($this->invoice)
                : null,
        ];
    }
}; ?>

<section class="w-full">
    @php
        $formatter = app(\App\Support\Formatter::class);
        $statusClass = match($effectiveStatus) {
            'draft' => 's-badge-zinc',
            'issued', 'partially_paid' => 's-badge-blue',
            'paid' => 's-badge-green',
            'overdue' => 's-badge-red',
            'void', 'credited' => 's-badge-amber',
            default => 's-badge-zinc',
        };
    @endphp

    <x-signals.page-header :title="$invoice->number">
        <x-slot:breadcrumbs>
            <a href="{{ route('invoices.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Invoices</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $invoice->number }}</span>
        </x-slot:breadcrumbs>
        <x-slot:meta>
            <span class="s-badge {{ $statusClass }}">{{ config('invoices.statuses.'.$effectiveStatus.'.label', $effectiveStatus) }}</span>
            <span class="s-badge s-badge-zinc">{{ $invoice->typeEnum()->label() }}</span>
            @if(in_array($invoice->type, ['deposit', 'proforma'], true))
                <span class="s-chip">{{ ucfirst($invoice->type) }}</span>
            @endif
            @if($invoice->member)
                <a href="{{ route('members.show', $invoice->member) }}" wire:navigate class="s-chip">{{ $invoice->member->name }}</a>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            @if($canUpdate)
                <a href="{{ route('invoices.edit', $invoice) }}" wire:navigate class="s-btn s-btn-sm s-btn-ghost">Edit</a>
            @endif
            @if($canIssue)
                <button type="button" wire:click="issue" wire:confirm="Issue this invoice to the customer?" class="s-btn s-btn-sm s-btn-accent">Issue</button>
            @endif
            @if($canPay)
                <button type="button" wire:click="openPaymentModal" class="s-btn s-btn-sm s-btn-ghost">Record Payment</button>
            @endif
            @if($canCredit)
                <button type="button" wire:click="openCreditNoteModal" class="s-btn s-btn-sm s-btn-ghost">Credit Note</button>
            @endif
            @if($invoice->status !== 'draft')
                <button type="button" wire:click="downloadPdf" class="s-btn s-btn-sm s-btn-ghost">Download PDF</button>
                <button type="button" wire:click="emailInvoice" class="s-btn s-btn-sm s-btn-ghost">Email</button>
            @endif
            @if($canVoid)
                <button type="button" wire:click="voidInvoice" wire:confirm="Void this invoice?" class="s-btn s-btn-sm s-btn-ghost">Void</button>
            @endif
            @if($canDelete)
                <button type="button" wire:click="deleteDraft" wire:confirm="Delete this draft invoice?" class="s-btn s-btn-sm s-btn-ghost">Delete</button>
            @endif
        </x-slot:actions>
    </x-signals.page-header>

    @include('livewire.invoices.partials.invoice-tabs', ['invoice' => $invoice, 'activeTab' => 'overview'])

    <div class="grid grid-cols-1 gap-6 px-6 py-4 lg:grid-cols-[1fr_280px] max-md:px-5 max-sm:px-3">
        <div class="space-y-6">
            <x-signals.panel title="Line Items">
                @if($invoice->items->isEmpty())
                    <p class="text-sm text-[var(--text-muted)]">No line items yet.</p>
                @else
                    <x-signals.table-wrap>
                        <table class="s-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Discount</th>
                                    <th class="text-right">Tax</th>
                                    <th class="text-right">Total</th>
                                    @costs
                                        <th class="text-right">Margin</th>
                                    @endcosts
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->items as $item)
                                    <tr wire:key="item-{{ $item->id }}">
                                        <td>{{ $item->description }}</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->unit_price, $item->currency_code) }}</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->discount, $item->currency_code) }}</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->tax, $item->currency_code) }}</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->total, $item->currency_code) }}</td>
                                        @costs
                                            @php($lineMargin = $margin?->line($item->id))
                                            <td class="text-right" style="font-family: var(--font-mono);">
                                                @if($lineMargin?->has_basis)
                                                    {{ $formatter->money($lineMargin->margin_minor, $item->currency_code) }}
                                                    <span class="text-[var(--text-muted)]">({{ $lineMargin->margin_percent }})</span>
                                                @else
                                                    <span title="No cost data">—</span>
                                                @endif
                                            </td>
                                        @endcosts
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-signals.table-wrap>
                @endif
            </x-signals.panel>

            <x-signals.panel title="Payments">
                @if($invoice->payments->isEmpty())
                    <p class="text-sm text-[var(--text-muted)]">No payments recorded.</p>
                @else
                    <x-signals.table-wrap>
                        <table class="s-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Reference</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->payments as $payment)
                                    <tr wire:key="payment-{{ $payment->id }}">
                                        <td>@localdatetime($payment->paid_at)</td>
                                        <td>{{ $payment->paymentMethodEnum()->label() }}</td>
                                        <td>{{ $payment->reference ?? '—' }}</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($payment->amount, $invoice->currency_code) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-signals.table-wrap>
                @endif
            </x-signals.panel>

            <x-signals.panel title="Credit Notes">
                @if($invoice->creditNotes->isEmpty())
                    <p class="text-sm text-[var(--text-muted)]">No credit notes issued.</p>
                @else
                    <x-signals.table-wrap>
                        <table class="s-table">
                            <thead>
                                <tr>
                                    <th>Number</th>
                                    <th>Status</th>
                                    <th>Issued</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->creditNotes as $creditNote)
                                    <tr wire:key="cn-{{ $creditNote->id }}">
                                        <td>{{ $creditNote->number }}</td>
                                        <td>{{ $creditNote->status }}</td>
                                        <td>@localdatetime($creditNote->issued_at)</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($creditNote->total, $creditNote->currency_code) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-signals.table-wrap>
                @endif
            </x-signals.panel>
        </div>

        <div class="space-y-6">
            <x-signals.panel title="Summary">
                <x-signals.data-list layout="vertical">
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Subtotal</div>
                        <div class="s-data-list-value" style="font-family: var(--font-mono);">{{ $formatter->money($invoice->subtotal, $invoice->currency_code) }}</div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Tax</div>
                        <div class="s-data-list-value" style="font-family: var(--font-mono);">{{ $formatter->money($invoice->tax_total, $invoice->currency_code) }}</div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Total</div>
                        <div class="s-data-list-value" style="font-family: var(--font-mono);">{{ $formatter->money($invoice->total, $invoice->currency_code) }}</div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Paid</div>
                        <div class="s-data-list-value" style="font-family: var(--font-mono);">{{ $formatter->money($invoice->amount_paid, $invoice->currency_code) }}</div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Outstanding</div>
                        <div class="s-data-list-value" style="font-family: var(--font-mono);">{{ $formatter->money($invoice->amount_outstanding, $invoice->currency_code) }}</div>
                    </div>
                    @costs
                        @if($margin !== null)
                            <div class="s-data-list-item">
                                <div class="s-data-list-label">Margin</div>
                                <div class="s-data-list-value" style="font-family: var(--font-mono);">
                                    {{ $formatter->money($margin->margin_minor, $invoice->currency_code) }}
                                    @if($margin->margin_percent !== null)
                                        <span class="text-[var(--text-muted)]">({{ $margin->margin_percent }})</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endcosts
                </x-signals.data-list>
            </x-signals.panel>

            <x-signals.panel title="Details">
                <x-signals.data-list layout="vertical">
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Store</div>
                        <div class="s-data-list-value">{{ $invoice->store?->name ?? '—' }}</div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Issued</div>
                        <div class="s-data-list-value">
                            @if($invoice->issued_at)
                                @localdatetime($invoice->issued_at)
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="s-data-list-item">
                        <div class="s-data-list-label">Due</div>
                        <div class="s-data-list-value">
                            @if($invoice->due_at)
                                @localdatetime($invoice->due_at)
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    @if($invoice->opportunity)
                        <div class="s-data-list-item">
                            <div class="s-data-list-label">Opportunity</div>
                            <div class="s-data-list-value">
                                <a href="{{ route('opportunities.show', $invoice->opportunity) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $invoice->opportunity->subject }}</a>
                            </div>
                        </div>
                    @endif
                    @if($invoice->reference)
                        <div class="s-data-list-item">
                            <div class="s-data-list-label">Reference</div>
                            <div class="s-data-list-value">{{ $invoice->reference }}</div>
                        </div>
                    @endif
                </x-signals.data-list>
            </x-signals.panel>
        </div>
    </div>

    <flux:modal wire:model="showPaymentModal" class="max-w-md">
        <flux:heading size="lg">Record Payment</flux:heading>
        <form wire:submit="recordPayment" class="mt-4 space-y-4">
            <flux:field>
                <flux:label>Amount ({{ $invoice->currency_code }})</flux:label>
                <flux:input wire:model="paymentAmount" type="text" inputmode="decimal" />
                <flux:error name="paymentAmount" />
            </flux:field>
            <flux:field>
                <flux:label>Payment method</flux:label>
                <flux:select wire:model="paymentMethod">
                    @foreach($paymentMethods as $method)
                        <flux:select.option :value="$method->value">{{ $method->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:label>Reference</flux:label>
                <flux:input wire:model="paymentReference" />
            </flux:field>
            <div class="flex gap-2 justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showPaymentModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Record</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showCreditNoteModal" class="max-w-lg">
        <flux:heading size="lg">Issue Credit Note</flux:heading>
        <form wire:submit="issueCreditNote" class="mt-4 space-y-4">
            <flux:checkbox wire:model.live="fullCreditNote" label="Full credit (all line items)" />

            @if(! $fullCreditNote)
                <div class="space-y-3">
                    <p class="text-sm text-[var(--text-muted)]">Enter the quantity to credit for each line.</p>

                    @foreach($invoice->items as $item)
                        @php($remaining = $this->remainingCreditableQuantity($item->id))
                        @if(bccomp($remaining, '0', 2) > 0)
                            <flux:field wire:key="credit-line-{{ $item->id }}">
                                <flux:label>
                                    {{ $item->description }}
                                    <span class="text-[var(--text-muted)]">
                                        (qty {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }},
                                        {{ $formatter->money($item->total, $item->currency_code) }},
                                        max {{ $remaining }})
                                    </span>
                                </flux:label>
                                <flux:input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="{{ $remaining }}"
                                    wire:model="creditNoteLines.{{ $item->id }}.quantity"
                                />
                                <input type="hidden" wire:model="creditNoteLines.{{ $item->id }}.invoice_item_id" value="{{ $item->id }}" />
                                <flux:error name="creditNoteLines.{{ $item->id }}.quantity" />
                            </flux:field>
                        @endif
                    @endforeach

                    <flux:error name="creditNoteLines" />
                </div>
            @endif

            <flux:field>
                <flux:label>Notes</flux:label>
                <flux:textarea wire:model="creditNoteNotes" rows="3" />
            </flux:field>
            <div class="flex gap-2 justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showCreditNoteModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Issue Credit Note</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
