<?php

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceStatusRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Invoices')] class extends Component {
    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url]
    public string $search = '';

    /** @var Collection<string, int> */
    public Collection $statusCounts;

    public int $totalCount = 0;

    public function mount(): void
    {
        Gate::authorize('invoices.access');

        $this->refreshStatusCounts();
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ! array_key_exists($status, config('invoices.statuses', []))) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function refreshStatusCounts(): void
    {
        $this->statusCounts = Invoice::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->totalCount = (int) $this->statusCounts->sum();
    }

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function invoices(): LengthAwarePaginator
    {
        return Invoice::query()
            ->with(['member', 'store'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q): void {
                $q->where(function ($inner): void {
                    $inner->where('number', 'ilike', '%'.$this->search.'%')
                        ->orWhere('reference', 'ilike', '%'.$this->search.'%')
                        ->orWhereHas('member', fn ($m) => $m->where('name', 'ilike', '%'.$this->search.'%'));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'statuses' => app(InvoiceStatusRegistry::class)->statuses(),
            'types' => InvoiceType::cases(),
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Invoices">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Finance</span>
        </x-slot:meta>
        <x-slot:actions>
            @action('invoices.create')
                <a href="{{ route('invoices.create') }}" wire:navigate class="s-btn s-btn-sm s-btn-accent">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
                    New Draft
                </a>
            @endaction
        </x-slot:actions>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <button wire:click="setStatusFilter('')" class="s-chip {{ $statusFilter === '' ? 'on' : '' }}">
                All <span style="opacity: 0.6;">{{ $totalCount }}</span>
            </button>
            @foreach($statuses as $statusKey => $statusMeta)
                <button wire:click="setStatusFilter('{{ $statusKey }}')" class="s-chip {{ $statusFilter === $statusKey ? 'on' : '' }}">
                    {{ $statusMeta['label'] ?? $statusKey }}
                    <span style="opacity: 0.6;">{{ $statusCounts[$statusKey] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number, reference, or member…" icon="magnifying-glass" />
        </div>

        @php $formatter = app(\App\Support\Formatter::class); @endphp

        @if($this->invoices()->isEmpty())
            <x-signals.empty title="No invoices found" description="Create a draft invoice or generate one from an opportunity." />
        @else
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Member</th>
                            <th>Store</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Outstanding</th>
                            <th>Issued</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->invoices() as $invoice)
                            <tr wire:key="invoice-{{ $invoice->id }}">
                                <td>
                                    <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="font-medium text-[var(--link)] hover:underline">
                                        {{ $invoice->number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="s-badge s-badge-zinc">{{ $invoice->typeEnum()->label() }}</span>
                                </td>
                                <td>
                                    @php
                                        $effective = $invoice->effectiveStatus();
                                        $statusClass = match($effective) {
                                            'draft' => 's-badge-zinc',
                                            'issued', 'partially_paid' => 's-badge-blue',
                                            'paid' => 's-badge-green',
                                            'overdue' => 's-badge-red',
                                            'void', 'credited' => 's-badge-amber',
                                            default => 's-badge-zinc',
                                        };
                                    @endphp
                                    <span class="s-badge {{ $statusClass }}">{{ config('invoices.statuses.'.$effective.'.label', $effective) }}</span>
                                </td>
                                <td>{{ $invoice->member?->name ?? '—' }}</td>
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

            <div class="mt-4">
                {{ $this->invoices()->links() }}
            </div>
        @endif
    </div>
</section>
