<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock\Presentation\Livewire;

use App\Domain\VirtualStock\Models\VirtualStockIntake;
use App\Domain\VirtualStock\Presentation\Columns\VirtualStockIntakeColumnRegistry;
use App\Domain\VirtualStock\Services\VirtualStockStatusRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Virtual Stock')] final class Index extends Component
{
    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', VirtualStockIntake::class);
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ! array_key_exists($status, config('virtual_stock.statuses', []))) {
            return;
        }

        $this->statusFilter = $status;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = app(VirtualStockIntakeColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)->sortable ?? false,
            'filterable' => $registry->get($key)->filterable ?? false,
        ];

        $statuses = app(VirtualStockStatusRegistry::class)->statuses();
        $statusOptions = collect($statuses)
            ->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label']])
            ->all();

        $columns = [
            ['key' => 'number', 'label' => 'Number', ...$columnMeta('number'), 'filter_type' => 'text', 'view' => 'virtual-stock::partials.column-number'],
            ['key' => 'product_id', 'label' => 'Product', ...$columnMeta('product_id'), 'view' => 'virtual-stock::partials.column-product'],
            ['key' => 'store_id', 'label' => 'Store', ...$columnMeta('store_id'), 'view' => 'virtual-stock::partials.column-store'],
            ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'filter_type' => 'select', 'filter_options' => $statusOptions, 'view' => 'virtual-stock::partials.column-status'],
            ['key' => 'available_from', 'label' => 'Window', 'view' => 'virtual-stock::partials.column-window'],
            ['key' => 'quantity', 'label' => 'Qty', ...$columnMeta('quantity'), 'view' => 'virtual-stock::partials.column-quantity'],
        ];

        if (Gate::check('costs.view')) {
            array_splice($columns, 5, 0, [[
                'key' => 'unit_cost',
                'label' => 'Total Cost',
                ...$columnMeta('unit_cost'),
                'view' => 'virtual-stock::partials.column-total-cost',
            ]]);
        }

        return [
            'statuses' => $statuses,
            'columns' => $columns,
            'scopes' => $this->statusFilter !== '' ? ['ofStatus' => $this->statusFilter] : [],
        ];
    }

    public function render(): View
    {
        return view('virtual-stock::index', $this->with());
    }
}
