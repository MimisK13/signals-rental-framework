<?php

declare(strict_types=1);

namespace App\Domain\Assets\Repairs\Presentation\Livewire;

use App\Domain\Assets\Repairs\Models\Repair;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Index extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', Repair::class);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'repairs' => Repair::query()
                ->with(['product', 'store', 'asset.currentStockLevel'])
                ->latest('reported_at')
                ->paginate(25),
        ];
    }

    public function render(): View
    {
        return view('repairs::index', $this->with());
    }
}
