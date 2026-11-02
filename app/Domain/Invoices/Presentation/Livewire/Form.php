<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Presentation\Livewire;

use App\Domain\Invoices\Actions\CreateDraftInvoice;
use App\Domain\Invoices\Actions\UpdateDraftInvoice;
use App\Domain\Invoices\Data\CreateDraftInvoiceData;
use App\Domain\Invoices\Data\UpdateDraftInvoiceData;
use App\Domain\Invoices\Enums\InvoiceType;
use App\Domain\Invoices\Models\Invoice;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Store;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Form extends Component
{
    public ?int $invoiceId = null;

    public int $memberId = 0;

    public int $storeId = 0;

    public string $type = 'standard';

    public ?string $notes = null;

    public ?string $reference = null;

    public function mount(?Invoice $invoice = null): void
    {
        if ($invoice?->exists) {
            Gate::authorize('update', $invoice);
            $invoice->ensureDraft();
            $this->invoiceId = $invoice->id;
            $this->memberId = $invoice->member_id;
            $this->storeId = $invoice->store_id;
            $this->type = $invoice->type;
            $this->notes = $invoice->notes;
            $this->reference = $invoice->reference;
        } else {
            Gate::authorize('invoices.create');
            $defaultStore = Store::query()->orderBy('id')->value('id');
            $this->storeId = (int) ($defaultStore ?? 0);
        }
    }

    public function save(): void
    {
        $this->validate([
            'memberId' => ['required', 'integer', Rule::exists('members', 'id')->where('membership_type', MembershipType::Organisation->value)],
            'storeId' => ['required', 'integer', Rule::exists('stores', 'id')],
            'type' => ['required', Rule::enum(InvoiceType::class)],
            'notes' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->invoiceId !== null) {
            $invoice = Invoice::query()->findOrFail($this->invoiceId);
            (new UpdateDraftInvoice)($invoice, UpdateDraftInvoiceData::from([
                'notes' => $this->notes,
                'reference' => $this->reference,
                'notes_provided' => true,
                'reference_provided' => true,
            ]));
            $this->redirect(route('invoices.show', $invoice), navigate: true);

            return;
        }

        $result = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
            'member_id' => $this->memberId,
            'store_id' => $this->storeId,
            'type' => $this->type,
            'notes' => $this->notes,
            'reference' => $this->reference,
        ]));

        $this->redirect(route('invoices.show', $result->id), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->invoiceId !== null,
            'members' => Member::query()->where('membership_type', MembershipType::Organisation->value)->orderBy('name')->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'types' => InvoiceType::cases(),
        ];
    }

    public function render(): ViewContract
    {
        return view('invoices::invoices.form', $this->with());
    }
}
