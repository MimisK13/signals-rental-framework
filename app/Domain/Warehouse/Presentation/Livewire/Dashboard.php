<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Presentation\Livewire;

use App\Domain\Stores\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Warehouse')]
final class Dashboard extends Component
{
    #[Url(as: 'store')]
    public string $storeFilter = '';

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'until')]
    public string $until = '';

    #[Url]
    public string $search = '';

    /** @var Collection<int, Store>|null */
    private ?Collection $storesCache = null;

    public function mount(): void
    {
        Gate::authorize('opportunities.view');
    }

    public function resetFilters(): void
    {
        $this->storeFilter = '';
        $this->from = '';
        $this->until = '';
        $this->search = '';
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'stores' => $this->storesCache ??= $this->stores(),
            'columns' => [
                ['key' => 'number', 'label' => 'Order', 'sortable' => true, 'view' => 'warehouse::partials.column-order'],
                ['key' => 'member_id', 'label' => 'Customer', 'view' => 'warehouse::partials.column-customer'],
                ['key' => 'store_id', 'label' => 'Store', 'view' => 'warehouse::partials.column-store'],
                ['key' => 'planning_starts_at', 'label' => 'Planning period', 'sortable' => true, 'view' => 'warehouse::partials.column-period'],
                ['key' => 'items_count', 'label' => 'Lines', 'sortable' => true, 'view' => 'warehouse::partials.column-lines'],
                ['key' => 'status', 'label' => 'Status', 'view' => 'warehouse::partials.column-status'],
                ['key' => 'has_shortage', 'label' => 'Attention', 'view' => 'warehouse::partials.column-attention'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                'warehouseQueue' => [
                    'accessible_store_ids' => auth()->user()->accessibleStoreIds(),
                    'store' => $this->storeFilter,
                    'from' => $this->from,
                    'until' => $this->until,
                    'search' => $this->search,
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, Store>
     */
    private function stores(): Collection
    {
        if ($this->storesCache !== null) {
            return $this->storesCache;
        }

        $query = Store::query()->orderBy('name');
        $accessibleStoreIds = auth()->user()->accessibleStoreIds();

        if ($accessibleStoreIds !== null) {
            $query->whereIn('id', $accessibleStoreIds);
        }

        return $this->storesCache = $query->get(['id', 'name']);
    }

    public function render(): View
    {
        return view('warehouse::index', $this->with());
    }
}
