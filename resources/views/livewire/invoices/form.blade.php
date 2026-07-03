<?php

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\UpdateDraftInvoice;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Invoices\UpdateDraftInvoiceData;
use App\Enums\InvoiceType;
use App\Enums\MembershipType;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Store;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?int $invoiceId = null;

    public int $memberId = 0;

    public int $storeId = 0;

    public string $type = 'standard';

    public ?string $notes = null;

    public ?string $reference = null;

    public function mount(?Invoice $invoice = null): void
    {
        if ($invoice !== null) {
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
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$isEditing ? 'Edit Draft Invoice' : 'New Draft Invoice'">
        <x-slot:breadcrumbs>
            <a href="{{ route('invoices.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Invoices</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $isEditing ? 'Edit' : 'Create' }}</span>
        </x-slot:breadcrumbs>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <flux:field>
                <flux:label>Member (Organisation)</flux:label>
                <flux:select wire:model="memberId" :disabled="$isEditing">
                    <flux:select.option value="0">Select member…</flux:select.option>
                    @foreach($members as $member)
                        <flux:select.option :value="$member->id">{{ $member->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="memberId" />
            </flux:field>

            <flux:field>
                <flux:label>Store</flux:label>
                <flux:select wire:model="storeId" :disabled="$isEditing">
                    @foreach($stores as $store)
                        <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="storeId" />
            </flux:field>

            <flux:field>
                <flux:label>Type</flux:label>
                <flux:select wire:model="type" :disabled="$isEditing">
                    @foreach($types as $invoiceType)
                        <flux:select.option :value="$invoiceType->value">{{ $invoiceType->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="type" />
            </flux:field>

            <flux:field>
                <flux:label>Reference</flux:label>
                <flux:input wire:model="reference" />
                <flux:error name="reference" />
            </flux:field>

            <flux:field>
                <flux:label>Notes</flux:label>
                <flux:textarea wire:model="notes" rows="4" />
                <flux:error name="notes" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">Save</flux:button>
                <flux:button variant="ghost" href="{{ $isEditing ? route('invoices.show', $invoiceId) : route('invoices.index') }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </div>
</section>
