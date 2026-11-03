<?php

declare(strict_types=1);

namespace App\Domain\Products\Presentation\Livewire;

use App\Domain\Products\Actions\CreateAccessory;
use App\Domain\Products\Actions\DeleteAccessory;
use App\Domain\Products\Data\CreateAccessoryData;
use App\Domain\Products\Models\Product;
use App\Services\Api\RansackFilter;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class Accessories extends Component
{
    public Product $product;

    public string $accessorySearch = '';

    public ?int $selectedAccessoryId = null;

    public int $accessoryQuantity = 1;

    public function mount(Product $product): void
    {
        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments']);
        $this->product->load(['accessories.accessoryProduct']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Accessories');
    }

    public function addAccessory(): void
    {
        Gate::authorize('products.edit');

        $this->validate([
            'selectedAccessoryId' => ['required', 'integer', 'exists:products,id'],
            'accessoryQuantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($this->selectedAccessoryId === $this->product->id) {
            $this->addError('selectedAccessoryId', 'A product cannot be an accessory of itself.');

            return;
        }

        $exists = $this->product->accessories()
            ->where('accessory_product_id', $this->selectedAccessoryId)
            ->exists();

        if ($exists) {
            $this->addError('selectedAccessoryId', 'This product is already an accessory.');

            return;
        }

        (new CreateAccessory)(CreateAccessoryData::from([
            'product_id' => $this->product->id,
            'accessory_product_id' => $this->selectedAccessoryId,
            'quantity' => (string) $this->accessoryQuantity,
        ]));

        $this->resetForm();
        $this->product->load(['accessories.accessoryProduct']);
        $this->product->loadCount('accessories');
        $this->dispatch('close-modal', 'add-accessory');
    }

    /**
     * Reset the add-accessory form fields to their defaults.
     */
    public function resetForm(): void
    {
        $this->reset(['accessorySearch', 'selectedAccessoryId', 'accessoryQuantity']);
        $this->accessoryQuantity = 1;
    }

    public function removeAccessory(int $accessoryId): void
    {
        Gate::authorize('products.edit');

        $accessory = $this->product->accessories()->whereKey($accessoryId)->first();

        if ($accessory === null) {
            return;
        }

        (new DeleteAccessory)($accessory);

        $this->product->load(['accessories.accessoryProduct']);
        $this->product->loadCount('accessories');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $searchResults = [];
        if (strlen($this->accessorySearch) >= 2) {
            $searchResults = Product::query()
                ->where('id', '!=', $this->product->id)
                ->where('name', 'ilike', '%'.RansackFilter::escapeLike($this->accessorySearch).'%')
                ->whereNotIn('id', $this->product->accessories->pluck('accessory_product_id'))
                ->limit(10)
                ->get(['id', 'name', 'product_type']);
        }

        return [
            'searchResults' => $searchResults,
        ];
    }

    public function render(): View
    {
        return view('products::accessories');
    }
}
