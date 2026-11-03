<?php

declare(strict_types=1);

namespace App\Domain\Stock\Presentation\Livewire;

use App\Domain\Products\Models\Product;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ProductStock extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Stock');
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'columns' => [
                ['key' => 'item_name', 'label' => 'Item Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'products::partials.stock-item-name'],
                ['key' => 'asset_number', 'label' => 'Asset / Barcode', 'sortable' => true, 'view' => 'stock::partials.column-asset-number'],
                ['key' => 'serial_number', 'label' => 'Serial #', 'sortable' => true, 'view' => 'stock::partials.column-serial-number'],
                ['key' => 'location', 'label' => 'Location', 'sortable' => true],
                ['key' => 'quantity_held', 'label' => 'Held', 'sortable' => true, 'view' => 'stock::partials.column-quantity-held'],
                ['key' => 'quantity_allocated', 'label' => 'Allocated', 'sortable' => true, 'view' => 'stock::partials.column-quantity-allocated'],
                ['key' => 'quantity_unavailable', 'label' => 'Unavailable', 'sortable' => true, 'view' => 'stock::partials.column-quantity-unavailable'],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                'forProduct' => $this->product->id,
            ],
        ];
    }

    public function render(): View
    {
        return view('stock::product-stock');
    }
}
