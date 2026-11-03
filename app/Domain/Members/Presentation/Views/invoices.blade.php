<section class="w-full">
    @include('members::partials.member-header', ['member' => $member, 'subpage' => 'Invoices'])
    @include('members::partials.member-tabs', ['member' => $member, 'activeTab' => 'invoices'])

    @php $formatter = app(\App\Support\Formatter::class); @endphp

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <x-signals.panel title="Outstanding Balance" class="min-w-[240px]">
                <div class="text-2xl font-semibold" style="font-family: var(--font-mono);">
                    {{ $formatter->money($outstandingMinor, $currencyCode) }}
                </div>
            </x-signals.panel>

            @can('members.view')
                <div class="flex items-end gap-2">
                    <flux:field>
                        <flux:label>Statement store</flux:label>
                        <flux:select wire:model="statementStoreId">
                            @foreach($stores as $store)
                                <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                    <flux:button wire:click="emailStatement" variant="primary">Email Statement</flux:button>
                </div>
            @endcan
        </div>

        @if($invoices->isEmpty())
            <x-dense::empty-state title="No invoices" description="This member has no invoices yet." />
        @else
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Store</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Outstanding</th>
                            <th>Issued</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr wire:key="member-invoice-{{ $invoice->id }}">
                                <td>
                                    <a href="{{ route('invoices.show', $invoice) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">{{ $invoice->number }}</a>
                                </td>
                                <td><span class="s-badge s-badge-zinc">{{ $invoice->typeEnum()->label() }}</span></td>
                                <td>{{ config('invoices.statuses.'.$invoice->effectiveStatus().'.label', $invoice->status) }}</td>
                                <td>{{ $invoice->store?->name ?? '—' }}</td>
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
</section>
