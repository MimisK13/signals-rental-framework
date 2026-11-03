<section class="w-full">
    @include('members::partials.member-header', ['member' => $member, 'subpage' => 'Quotes'])
    @include('members::partials.member-tabs', ['member' => $member, 'activeTab' => 'quotes'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-dense::table-card title="Quotations" :count="$quotations->total()">
            @if($quotations->isEmpty())
                <x-dense::empty-state title="No quotations" description="No quotations have been created for this member yet." />
            @else
                <div class="overflow-x-auto">
                    <table class="s-table w-full">
                        <thead>
                            <tr>
                                <th>Quotation</th>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Dates</th>
                                <th class="text-right">Total</th>
                                <th class="w-12"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quotations as $quotation)
                                <tr wire:key="member-quotation-{{ $quotation->id }}">
                                    <td>
                                        <a href="{{ route('quotations.edit', $quotation) }}" wire:navigate.hover class="font-medium hover:underline">
                                            {{ $quotation->number }} · {{ $quotation->subject }}
                                        </a>
                                    </td>
                                    <td>{{ $quotation->project?->name ?? 'Standalone' }}</td>
                                    <td><x-dense::status variant="outline" :label="$quotation->status->label()" /></td>
                                    <td class="whitespace-nowrap">
                                        {{ $quotation->starts_at?->format('d M Y') ?? '—' }}{{ $quotation->ends_at ? ' – '.$quotation->ends_at->format('d M Y') : '' }}
                                    </td>
                                    <td class="text-right font-mono tabular-nums">{{ $quotation->formatMoneyCost('charge_total') }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('quotations.edit', $quotation) }}" wire:navigate.hover class="s-btn-ghost s-btn-xs">Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($quotations->hasPages())
                <x-slot:footer>{{ $quotations->links() }}</x-slot:footer>
            @endif
        </x-dense::table-card>
    </div>
</section>
