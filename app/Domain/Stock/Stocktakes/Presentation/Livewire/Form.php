<?php

declare(strict_types=1);

namespace App\Domain\Stock\Stocktakes\Presentation\Livewire;

use App\Domain\Stock\Stocktakes\Actions\CreateStocktake;
use App\Domain\Stock\Stocktakes\Data\CreateStocktakeData;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Form extends Component
{
    public ?int $storeId = null;

    public string $notes = '';

    public function mount(): void
    {
        Gate::authorize('stock.count');

        $stores = $this->stores();
        if ($stores->count() === 1) {
            $this->storeId = $stores->first()->id;
        }
    }

    /** @return Collection<int, Store> */
    public function stores(): Collection
    {
        $storeIds = auth()->user()?->accessibleStoreIds();

        return Store::query()
            ->when($storeIds !== null, fn ($query) => $query->whereIn('id', $storeIds))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(): void
    {
        $this->validate([
            'storeId' => ['required', 'integer', 'exists:stores,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $stocktake = (new CreateStocktake)(CreateStocktakeData::from([
            'store_id' => $this->storeId,
            'notes' => $this->notes,
        ]));

        $this->redirectRoute('stocktakes.show', $stocktake, navigate: true);
    }

    public function render(): View
    {
        return view('stocktakes::form');
    }
}
