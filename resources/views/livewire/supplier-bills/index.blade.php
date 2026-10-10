<?php

use App\Models\SupplierBill;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public function mount(): void
    {
        Gate::authorize('viewAny', SupplierBill::class);
    }

    public function with(): array
    {
        return [
            'bills' => SupplierBill::query()->with(['supplier', 'purchaseOrder'])->latest('issued_at')->latest('id')->paginate(25),
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Supplier Bills">
        <x-slot:actions>
            @can('create', App\Models\SupplierBill::class)
                <a href="{{ route('supplier-bills.create') }}" wire:navigate.hover class="s-btn s-btn-primary">New Supplier Bill</a>
            @endcan
        </x-slot:actions>
    </x-signals.page-header>

    <div class="px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.card>
            <x-signals.table-wrap>
                <table class="s-table w-full">
                    <thead><tr><th>Document</th><th>Supplier</th><th>Status</th><th>Issued</th><th>Total</th><th>Purchase Order</th></tr></thead>
                    <tbody>
                        @forelse($bills as $bill)
                            <tr wire:key="supplier-bill-{{ $bill->id }}">
                                <td><a href="{{ route('supplier-bills.show', $bill) }}" wire:navigate.hover class="font-medium text-[var(--link)] hover:underline">{{ $bill->document_number }}</a></td>
                                <td>{{ $bill->supplier->name }}</td>
                                <td>{{ $bill->status->label() }}</td>
                                <td>{{ $bill->issued_at?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $bill->formatMoneyCost('total') }}</td>
                                <td>{{ $bill->purchaseOrder?->number ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-[var(--text-muted)]">No supplier bills recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-signals.table-wrap>
            <div class="mt-4">{{ $bills->links() }}</div>
        </x-signals.card>
    </div>
</section>
