<section class="relative min-w-0 w-full">
    <x-dense::page-header title="Quotations">
        <x-slot:actions>
            @can('create', \App\Domain\Quotations\Models\Quotation::class)
                <x-dense::button :href="route('quotations.create')" variant="accent" size="sm" wire:navigate.hover>
                    New Quotation
                </x-dense::button>
            @endcan
        </x-slot:actions>
    </x-dense::page-header>

    <div class="px-6 pb-6 max-md:px-5 max-sm:px-3">
        <div class="mb-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_240px]">
            <x-dense::input wire:model.live.debounce.300ms="search" placeholder="Search number, subject, or reference"
                aria-label="Search quotations" />
            <x-dense::select wire:model.live="status" aria-label="Quotation status">
                <option value="">All statuses</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </x-dense::select>
            <x-dense::select wire:model.live="project" aria-label="Project">
                <option value="">All Projects</option>
                @forelse($projects as $projectOption)
                    <option value="{{ $projectOption->id }}">{{ $projectOption->name }}</option>
                @empty
                @endforelse
            </x-dense::select>
        </div>

        <x-dense::table-card title="Quotations" :count="$quotations->total()">
            @if ($quotations->isEmpty())
                <x-dense::empty-state title="No quotations found"
                    description="Create a standalone quotation or start one from a Project." />
            @else
                <div class="overflow-x-auto">
                    <table class="s-table w-full">
                        <thead>
                            <tr>
                                <th>Quotation</th>
                                <th>Customer</th>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Dates</th>
                                <th class="text-right">Net total</th>
                                <th class="w-12"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quotations as $quotation)
                                <tr wire:key="quotation-{{ $quotation->id }}">
                                    <td>
                                        <a href="{{ route('quotations.edit', $quotation) }}" wire:navigate.hover
                                            class="font-medium hover:underline">
                                            {{ $quotation->number }} · {{ $quotation->subject }}
                                        </a>
                                    </td>
                                    <td>{{ $quotation->customer?->name ?? '—' }}</td>
                                    <td>{{ $quotation->project?->name ?? 'Standalone' }}</td>
                                    <td>
                                        <x-dense::status variant="outline" :color="match ($quotation->status->value) {
                                            'accepted' => 'green',
                                            'sent' => 'blue',
                                            'declined', 'cancelled', 'expired' => 'red',
                                            default => 'amber',
                                        }" :label="$quotation->status->label()" />
                                    </td>
                                    <td class="whitespace-nowrap">
                                        {{ $quotation->starts_at?->format('d M Y') ?? '—' }}{{ $quotation->ends_at ? ' – ' . $quotation->ends_at->format('d M Y') : '' }}
                                    </td>
                                    <td class="text-right font-mono tabular-nums">
                                        {{ $quotation->formatMoneyCost('charge_total') }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('quotations.edit', $quotation) }}" wire:navigate.hover
                                            class="s-btn-ghost s-btn-xs">Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <x-slot:footer>
                {{ $quotations->links() }}
            </x-slot:footer>
        </x-dense::table-card>
    </div>
</section>
