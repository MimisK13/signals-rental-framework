<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Presentation\Livewire;

use App\Domain\Invoices\Enums\InvoiceType;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Presentation\Tables\InvoiceColumnRegistry;
use App\Domain\Invoices\Services\InvoiceStatusRegistry;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Invoices')]
final class Index extends Component
{
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
            'sortable' => $registry->get($key)->sortable,
            'filterable' => $registry->get($key)->filterable,
        ];

        /** @var array<string, array{label?: string}> $statusConfig */
        $statusConfig = config('invoices.statuses', []);
        $statusOptions = collect($statusConfig)
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
                ['key' => 'number', 'label' => 'Number', ...$columnMeta('number'), 'filter_type' => 'text', 'view' => 'invoices::invoices.partials.column-number'],
                ['key' => 'type', 'label' => 'Type', ...$columnMeta('type'), 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'invoices::invoices.partials.column-type'],
                ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'filter_type' => 'select', 'filter_options' => $statusOptions, 'view' => 'invoices::invoices.partials.column-status'],
                ['key' => 'member_id', 'label' => 'Member', ...$columnMeta('member_id'), 'view' => 'invoices::invoices.partials.column-member'],
                ['key' => 'store_id', 'label' => 'Store', ...$columnMeta('store_id'), 'view' => 'invoices::invoices.partials.column-store'],
                ['key' => 'total', 'label' => 'Total', ...$columnMeta('total'), 'view' => 'invoices::invoices.partials.column-total'],
                ['key' => 'amount_outstanding', 'label' => 'Outstanding', ...$columnMeta('amount_outstanding'), 'view' => 'invoices::invoices.partials.column-outstanding'],
                ['key' => 'issued_at', 'label' => 'Issued', ...$columnMeta('issued_at'), 'view' => 'invoices::invoices.partials.column-issued'],
            ],
            'scopes' => match (true) {
                $this->statusFilter === 'overdue' => ['overdue' => true],
                $this->statusFilter !== '' => ['ofStatus' => $this->statusFilter],
                default => [],
            },
        ];
    }

    public function render(): ViewContract
    {
        return view('invoices::invoices.index', $this->with());
    }
}
