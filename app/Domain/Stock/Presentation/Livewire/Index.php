<?php

declare(strict_types=1);

namespace App\Domain\Stock\Presentation\Livewire;

use App\Domain\Stock\Actions\DeleteStockLevel;
use App\Domain\Stock\Models\StockLevel;
use Illuminate\Contracts\View\View as ViewContract;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Stock Levels')]
final class Index extends Component
{
    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function setStatusFilter(string $status): void
    {
        if (! in_array($status, ['', 'available', 'allocated', 'quarantined'])) {
            return;
        }

        $this->statusFilter = $status;
    }

    /**
     * Delete a single stock level from the row-actions menu.
     */
    public function deleteStockLevel(int $stockLevelId): void
    {
        $stockLevel = StockLevel::findOrFail($stockLevelId);
        (new DeleteStockLevel)($stockLevel);
        $this->dispatch('stock-level-deleted');
    }

    /**
     * Bulk delete the selected stock levels via the shared delete action.
     *
     * @param  array<int, int>  $ids
     */
    public function deleteSelected(array $ids): void
    {
        $action = new DeleteStockLevel;
        foreach ($ids as $id) {
            $stockLevel = StockLevel::find($id);
            if ($stockLevel !== null) {
                $action($stockLevel);
            }
        }
        $this->dispatch('stock-level-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'avatar', 'label' => '', 'view' => 'stock::partials.column-avatar', 'pairWith' => 'item_name'],
                ['key' => 'product', 'label' => 'Product', 'view' => 'stock::partials.column-product'],
                ['key' => 'item_name', 'label' => 'Item Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'stock::partials.column-item-name'],
                ['key' => 'store', 'label' => 'Store', 'view' => 'stock::partials.column-store'],
                ['key' => 'asset_number', 'label' => 'Asset / Barcode', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'stock::partials.column-asset-number'],
                ['key' => 'serial_number', 'label' => 'Serial #', 'sortable' => true, 'view' => 'stock::partials.column-serial-number'],
                ['key' => 'stock_method', 'label' => 'Method', 'view' => 'stock::partials.column-stock-method'],
                ['key' => 'quantity_held', 'label' => 'Held', 'sortable' => true, 'view' => 'stock::partials.column-quantity-held'],
                ['key' => 'quantity_allocated', 'label' => 'Allocated', 'sortable' => true, 'view' => 'stock::partials.column-quantity-allocated'],
                ['key' => 'quantity_unavailable', 'label' => 'Unavailable', 'sortable' => true, 'view' => 'stock::partials.column-quantity-unavailable'],
                ['key' => 'status', 'label' => 'Status', 'view' => 'stock::partials.column-status'],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => match ($this->statusFilter) {
                'available' => ['available' => true],
                'allocated' => ['allocated' => true],
                'quarantined' => ['quarantined' => true],
                default => [],
            },
        ];
    }

    public function render(): ViewContract
    {
        return view('stock::index', $this->with());
    }
}
