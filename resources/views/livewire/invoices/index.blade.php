<?php

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceStatusRegistry;
use App\Views\InvoiceColumnRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Invoices')] class extends Component {
    #[Url(as: 'status')]
    public string $statusFilter = '';

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

        $this->statusCounts['overdue'] = Invoice::query()->overdue()->count();

        $this->totalCount = (int) Invoice::query()->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = app(InvoiceColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)?->sortable ?? false,
            'filterable' => $registry->get($key)?->filterable ?? false,
        ];

        $statusOptions = collect(config('invoices.statuses', []))
            ->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label'] ?? $key])
            ->all();

        $typeOptions = collect(InvoiceType::cases())
            ->mapWithKeys(fn (InvoiceType $type): array => [$type->value => $type->label()])
            ->all();

        return [
            'statuses' => app(InvoiceStatusRegistry::class)->statuses(),
            'statusCounts' => $this->statusCounts,
            'totalCount' => $this->totalCount,
            'columns' => [
                ['key' => 'number', 'label' => 'Number', ...$columnMeta('number'), 'filter_type' => 'text', 'view' => 'livewire.invoices.partials.column-number'],
                ['key' => 'type', 'label' => 'Type', ...$columnMeta('type'), 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'livewire.invoices.partials.column-type'],
                ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'filter_type' => 'select', 'filter_options' => $statusOptions, 'view' => 'livewire.invoices.partials.column-status'],
                ['key' => 'member_id', 'label' => 'Member', ...$columnMeta('member_id'), 'view' => 'livewire.invoices.partials.column-member'],
                ['key' => 'store_id', 'label' => 'Store', ...$columnMeta('store_id'), 'view' => 'livewire.invoices.partials.column-store'],
                ['key' => 'total', 'label' => 'Total', ...$columnMeta('total'), 'view' => 'livewire.invoices.partials.column-total'],
                ['key' => 'amount_outstanding', 'label' => 'Outstanding', ...$columnMeta('amount_outstanding'), 'view' => 'livewire.invoices.partials.column-outstanding'],
                ['key' => 'issued_at', 'label' => 'Issued', ...$columnMeta('issued_at'), 'view' => 'livewire.invoices.partials.column-issued'],
            ],
            'scopes' => match (true) {
                $this->statusFilter === 'overdue' => ['overdue' => true],
                $this->statusFilter !== '' => ['ofStatus' => $this->statusFilter],
                default => [],
            },
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Invoices">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Finance</span>
        </x-slot:meta>
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

        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\Invoice::class"
            :searchable="['number', 'reference']"
            :with="['member', 'store']"
            :scopes="$scopes"
            default-sort="-created_at"
            empty-message="No invoices found."
            toolbar-view="livewire.invoices.partials.toolbar"
            entity-type="invoices"
            :key="'invoices-table-' . $statusFilter"
        />
    </div>
</section>
