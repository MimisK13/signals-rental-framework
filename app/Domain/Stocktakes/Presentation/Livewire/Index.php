<?php

declare(strict_types=1);

namespace App\Domain\Stocktakes\Presentation\Livewire;

use App\Domain\Stocktakes\Models\Stocktake;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Index extends Component
{
    public function mount(): void
    {
        Gate::authorize('stock.count');
    }

    /** @return Collection<int, Stocktake> */
    public function stocktakes(): Collection
    {
        $storeIds = auth()->user()?->accessibleStoreIds();

        return Stocktake::query()
            ->with(['store', 'startedBy', 'completedBy'])
            ->withCount('lines')
            ->when($storeIds !== null, fn ($query) => $query->whereIn('store_id', $storeIds))
            ->latest('id')
            ->get();
    }

    public function render(): View
    {
        return view('stocktakes::index');
    }
}
