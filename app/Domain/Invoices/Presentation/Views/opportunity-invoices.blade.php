
<section class="w-full">
    @include('project-opportunities::partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Invoices', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('project-opportunities::partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'invoices'])

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
            <x-dense::empty-state title="No invoices" description="Generate an invoice from this opportunity when ready." />
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

    @include('project-opportunities::partials.opportunity-action-modals', ['opportunity' => $opportunity])
</section>
