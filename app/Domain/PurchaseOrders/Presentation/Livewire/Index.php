<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders\Presentation\Livewire;

use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\PurchaseOrders\Presentation\Tables\PurchaseOrderColumnRegistry;
use App\Domain\PurchaseOrders\Services\PurchaseOrderStatusRegistry;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Purchase Orders')]
final class Index extends Component
{
    #[Url(as: 'status')]
    public string $statusFilter = '';

    /** @var Collection<string, int> */
    public Collection $statusCounts;

    public int $totalCount = 0;

    public function mount(): void
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $this->refreshStatusCounts();
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ! array_key_exists($status, config('purchase_orders.statuses', []))) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function refreshStatusCounts(): void
    {
        $this->statusCounts = PurchaseOrder::query()
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
        $registry = app(PurchaseOrderColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)->sortable,
            'filterable' => $registry->get($key)->filterable,
        ];

        /** @var array<string, array{label?: string}> $statusConfig */
        $statusConfig = config('purchase_orders.statuses', []);
        $statusOptions = collect($statusConfig)
            ->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label'] ?? $key])
            ->all();

        /** @var array<string, array{label?: string}> $typeConfig */
        $typeConfig = config('purchase_orders.types', []);
        $typeOptions = collect($typeConfig)
            ->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label'] ?? $key])
            ->all();

        return [
            'statuses' => app(PurchaseOrderStatusRegistry::class)->statuses(),
            'statusCounts' => $this->statusCounts,
            'totalCount' => $this->totalCount,
            'columns' => [
                ['key' => 'number', 'label' => 'Number', ...$columnMeta('number'), 'filter_type' => 'text', 'view' => 'purchase-orders::partials.column-number'],
                ['key' => 'po_type', 'label' => 'Type', ...$columnMeta('po_type'), 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'purchase-orders::partials.column-type'],
                ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'filter_type' => 'select', 'filter_options' => $statusOptions, 'view' => 'purchase-orders::partials.column-status'],
                ['key' => 'supplier_id', 'label' => 'Supplier', ...$columnMeta('supplier_id'), 'view' => 'purchase-orders::partials.column-supplier'],
                ['key' => 'store_id', 'label' => 'Store', ...$columnMeta('store_id'), 'view' => 'purchase-orders::partials.column-store'],
                ['key' => 'total', 'label' => 'Total', ...$columnMeta('total'), 'view' => 'purchase-orders::partials.column-total'],
                ['key' => 'expected_at', 'label' => 'Expected', ...$columnMeta('expected_at'), 'view' => 'purchase-orders::partials.column-expected'],
            ],
            'scopes' => $this->statusFilter !== '' ? ['ofStatus' => $this->statusFilter] : [],
        ];
    }

    public function render(): ViewContract
    {
        return view('purchase-orders::index', $this->with());
    }
}
