<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Presentation\Livewire;

use App\Domain\Communications\Actions\DispatchBillingCustomerCommunication;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Jobs\GenerateBillingIssueDocumentJob;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\DocumentTemplateResolver;
use App\Domain\Invoices\Actions\DeleteDraftInvoice;
use App\Domain\Invoices\Actions\IssueCreditNote;
use App\Domain\Invoices\Actions\IssueInvoice;
use App\Domain\Invoices\Actions\RecordPayment;
use App\Domain\Invoices\Actions\VoidInvoice;
use App\Domain\Invoices\Data\IssueCreditNoteData;
use App\Domain\Invoices\Data\RecordPaymentData;
use App\Domain\Invoices\Enums\PaymentMethod;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Services\InvoiceMarginCalculator;
use App\Enums\BillingCommunicationKind;
use App\Livewire\Concerns\HandlesVerbsStateErrors;
use App\Services\FileService;
use App\Support\CostVisibility;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Show extends Component
{
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

    public function downloadPdf(): ?RedirectResponse
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
            $url = app(FileService::class)->signedDocumentUrlOrNull($document);

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

            return null;
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

        return null;
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

    public function render(): ViewContract
    {
        return view('invoices::invoices.show', $this->with());
    }
}
