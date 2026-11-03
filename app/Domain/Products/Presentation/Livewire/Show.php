<?php

declare(strict_types=1);

namespace App\Domain\Products\Presentation\Livewire;

use App\Domain\Products\Models\Product;
use App\Domain\Stock\Models\StockLevel;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class Show extends Component
{
    public Product $product;

    public float $availableQuantity = 0.0;

    public function mount(Product $product): void
    {
        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments']);
        $this->product->load(['productGroup', 'taxClass', 'purchaseTaxClass', 'rentalRevenueGroup', 'saleRevenueGroup', 'subRentalCostGroup', 'purchaseCostGroup', 'countryOfOrigin']);

        $this->availableQuantity = (float) StockLevel::query()
            ->where('product_id', $this->product->id)
            ->selectRaw('COALESCE(SUM(quantity_held - quantity_allocated - quantity_unavailable), 0) as available')
            ->value('available');
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name);
    }

    public function render(): View
    {
        return view('products::show');
    }
}
