<?php

use App\Actions\Invoices\GenerateDepositInvoice;
use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\GenerateProformaInvoice;
use App\Data\Invoices\GenerateDepositInvoiceData;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\GenerateProformaInvoiceData;
use App\Enums\InvoiceType;
use App\Livewire\Concerns\HasOpportunityActions;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Services\Invoices\InvoiceSourceStateRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use HasOpportunityActions;

    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('invoices.view');

        $this->opportunity = $opportunity->load(['member', 'store']);
    }

    public function generateInvoice(): void
    {
        $result = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $this->opportunity->id,
        ]));

        $this->redirect(route('invoices.show', $result->id), navigate: true);
    }

    public function generateDeposit(): void
    {
        $result = (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
            'opportunity_id' => $this->opportunity->id,
            'amount_percentage' => (string) settings('invoices.deposit_percentage_default', '25'),
        ]));

        $this->redirect(route('invoices.show', $result->id), navigate: true);
    }

    public function generateProforma(): void
    {
        $result = (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
            'opportunity_id' => $this->opportunity->id,
        ]));

        $this->redirect(route('invoices.show', $result->id), navigate: true);
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Invoices');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $sourceRule = app(InvoiceSourceStateRule::class);
        $canCreate = Gate::check('invoices.create');
        $fullyInvoiced = $this->opportunity->isFullyQuantityInvoiced();

        return [
            ...$this->opportunityActionData(),
            'invoices' => Invoice::query()
                ->where('opportunity_id', $this->opportunity->id)
                ->with('member')
                ->orderByDesc('created_at')
                ->get(),
            'canGenerateStandard' => $canCreate
                && ! $fullyInvoiced
                && in_array($this->opportunity->state->value, $sourceRule->allowedStatesFor(InvoiceType::Standard), true),
            'canGenerateDeposit' => $canCreate
                && in_array($this->opportunity->state->value, $sourceRule->allowedStatesFor(InvoiceType::Deposit), true),
            'canGenerateProforma' => $canCreate
                && in_array($this->opportunity->state->value, $sourceRule->allowedStatesFor(InvoiceType::Proforma), true),
            'fullyInvoiced' => $fullyInvoiced,
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Invoices', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'invoices'])

    @php $formatter = app(\App\Support\Formatter::class); @endphp

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex flex-wrap gap-2">
            @if($canGenerateStandard)
                <button type="button" wire:click="generateInvoice" class="s-btn s-btn-sm s-btn-accent">Invoice</button>
            @elseif($fullyInvoiced)
                <span class="text-sm text-[var(--text-muted)]">Fully invoiced</span>
            @endif
            @if($canGenerateDeposit)
                <button type="button" wire:click="generateDeposit" class="s-btn s-btn-sm s-btn-ghost">Generate Deposit</button>
            @endif
            @if($canGenerateProforma)
                <button type="button" wire:click="generateProforma" class="s-btn s-btn-sm s-btn-ghost">Generate Pro-forma</button>
            @endif
        </div>

        @if($invoices->isEmpty())
            <x-signals.empty title="No invoices" description="Generate an invoice from this opportunity when ready." />
        @else
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Outstanding</th>
                            <th>Issued</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr wire:key="opp-invoice-{{ $invoice->id }}">
                                <td>
                                    <a href="{{ route('invoices.show', $invoice) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">{{ $invoice->number }}</a>
                                </td>
                                <td><span class="s-badge s-badge-zinc">{{ $invoice->typeEnum()->label() }}</span></td>
                                <td>{{ config('invoices.statuses.'.$invoice->effectiveStatus().'.label', $invoice->status) }}</td>
                                <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($invoice->total, $invoice->currency_code) }}</td>
                                <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($invoice->amount_outstanding, $invoice->currency_code) }}</td>
                                <td>
                                    @if($invoice->issued_at)
                                        @localdate($invoice->issued_at)
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-signals.table-wrap>
        @endif
    </div>

    @include('livewire.opportunities.partials.opportunity-action-modals', ['opportunity' => $opportunity])
</section>
