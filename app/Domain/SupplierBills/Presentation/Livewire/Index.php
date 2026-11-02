<?php

declare(strict_types=1);

namespace App\Domain\SupplierBills\Presentation\Livewire;

use App\Domain\SupplierBills\Models\SupplierBill;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Index extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', SupplierBill::class);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'bills' => SupplierBill::query()->with(['supplier', 'purchaseOrder'])->latest('issued_at')->latest('id')->paginate(25),
        ];
    }

    public function render(): View
    {
        return view('supplier-bills::index', $this->with());
    }
}
