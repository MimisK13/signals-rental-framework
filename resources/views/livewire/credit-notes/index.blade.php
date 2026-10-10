<?php

use App\Models\CreditNote;
use App\Services\Invoices\CreditNoteStatusRegistry;
use App\Views\CreditNoteColumnRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Credit Notes')] class extends Component {
    #[Url(as: 'status')]
    public string $statusFilter = '';

    /** @var Collection<string, int> */
    public Collection $statusCounts;

    public int $totalCount = 0;

    public function mount(): void
    {
        Gate::authorize('invoices.view');

        $this->refreshStatusCounts();
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ! array_key_exists($status, config('credit_notes.statuses', []))) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function refreshStatusCounts(): void
    {
        $this->statusCounts = CreditNote::query()
            ->forAccessibleStores()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->totalCount = (int) $this->statusCounts->sum();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = app(CreditNoteColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)?->sortable ?? false,
            'filterable' => $registry->get($key)?->filterable ?? false,
        ];

        $statusOptions = collect(config('credit_notes.statuses', []))
            ->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label'] ?? $key])
            ->all();

        $scopes = ['forAccessibleStores' => true];

        if ($this->statusFilter !== '') {
            $scopes['ofStatus'] = $this->statusFilter;
        }

        return [
            'statuses' => app(CreditNoteStatusRegistry::class)->statuses(),
            'statusCounts' => $this->statusCounts,
            'totalCount' => $this->totalCount,
            'columns' => [
                ['key' => 'number', 'label' => 'Number', ...$columnMeta('number'), 'filter_type' => 'text', 'view' => 'livewire.credit-notes.partials.column-number'],
                ['key' => 'invoice_id', 'label' => 'Invoice', ...$columnMeta('invoice_id'), 'view' => 'livewire.credit-notes.partials.column-invoice'],
                ['key' => 'member', 'label' => 'Member', 'sortable' => false, 'filterable' => false, 'view' => 'livewire.credit-notes.partials.column-member'],
                ['key' => 'issued_at', 'label' => 'Issued', ...$columnMeta('issued_at'), 'view' => 'livewire.credit-notes.partials.column-issued'],
                ['key' => 'total', 'label' => 'Total', ...$columnMeta('total'), 'view' => 'livewire.credit-notes.partials.column-total'],
                ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'filter_type' => 'select', 'filter_options' => $statusOptions, 'view' => 'livewire.credit-notes.partials.column-status'],
            ],
            'scopes' => $scopes,
        ];
    }
}; ?>

<section class="w-full">
    <x-dense::page-header title="Credit Notes">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Finance</span>
        </x-slot:meta>
    </x-dense::page-header>

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

        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\CreditNote::class"
            :searchable="['number']"
            :with="['invoice.member']"
            :scopes="$scopes"
            default-sort="-issued_at"
            empty-message="No credit notes found."
            toolbar-view="livewire.credit-notes.partials.toolbar"
            entity-type="credit_notes"
            :key="'credit-notes-table-' . $statusFilter"
        />
    </div>
</section>
