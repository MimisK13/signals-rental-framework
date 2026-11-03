<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock\Presentation\Livewire;

use App\Domain\Products\Models\Product;
use App\Domain\Stores\Models\Store;
use App\Domain\VirtualStock\Actions\CreateVirtualStockIntake;
use App\Domain\VirtualStock\Data\CreateVirtualStockIntakeData;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class Form extends Component
{
    public ?int $purchase_order_id = null;

    public ?int $purchase_order_item_id = null;

    public ?int $product_id = null;

    public ?int $store_id = null;

    public int $quantity = 1;

    public string $available_from = '';

    public string $available_until = '';

    public int $unit_cost = 0;

    public ?string $notes = null;

    public function mount(): void
    {
        Gate::authorize('create', VirtualStockIntake::class);

        $this->purchase_order_id = request()->integer('purchase_order_id') ?: null;
        $this->purchase_order_item_id = request()->integer('purchase_order_item_id') ?: null;
    }

    public function save(): void
    {
        $result = (new CreateVirtualStockIntake)(CreateVirtualStockIntakeData::from([
            'product_id' => (int) $this->product_id,
            'store_id' => (int) $this->store_id,
            'quantity' => $this->quantity,
            'available_from' => $this->available_from,
            'available_until' => $this->available_until,
            'unit_cost' => $this->unit_cost,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'notes' => $this->notes,
        ]));

        $this->redirect(route('virtual-stock.show', $result->id), navigate: true);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'products' => Product::query()->orderBy('name')->limit(100)->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function render(): View
    {
        return view('virtual-stock::form', $this->with());
    }
}
