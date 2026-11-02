<section class="w-full">
    <x-dense::page-header title="Repairs">
        <x-slot:actions>
            @can('create', \App\Domain\Repairs\Models\Repair::class)
                <a href="{{ route('repairs.create') }}" wire:navigate.hover class="s-btn s-btn-primary">New Repair</a>
            @endcan
        </x-slot:actions>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="s-table-wrap">
            <table class="s-table w-full">
                <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>Store</th>
                        <th>Scope</th>
                        <th>Status</th>
                        <th>Usable</th>
                        <th>Reported</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($repairs as $repair)
                        <tr wire:key="repair-{{ $repair->id }}">
                            <td>
                                <a href="{{ route('repairs.show', $repair) }}" wire:navigate.hover class="font-medium text-[var(--link)] hover:underline">
                                    {{ $repair->product?->name ?? '—' }}
                                </a>
                                @if($repair->asset?->currentStockLevel?->asset_number)
                                    <div class="text-xs text-[var(--text-muted)]">{{ $repair->asset->currentStockLevel->asset_number }}</div>
                                @endif
                            </td>
                            <td>{{ $repair->store?->name ?? '—' }}</td>
                            <td>{{ $repair->asset_id ? 'Serialized unit' : $repair->quantity.' units' }}</td>
                            <td>{{ $repair->status->label() }}</td>
                            <td>{{ $repair->is_usable ? 'Yes' : 'No' }}</td>
                            <td>{{ $repair->reported_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-[var(--text-muted)]">No repairs recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $repairs->links() }}</div>
    </div>
</section>
