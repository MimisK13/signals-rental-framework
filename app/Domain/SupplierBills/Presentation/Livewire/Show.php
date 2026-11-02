<?php

declare(strict_types=1);

namespace App\Domain\SupplierBills\Presentation\Livewire;

use App\Domain\SupplierBills\Actions\IssueSupplierBill;
use App\Domain\SupplierBills\Actions\VoidSupplierBill;
use App\Domain\SupplierBills\Models\SupplierBill;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Show extends Component
{
    public SupplierBill $supplierBill;

    public function mount(SupplierBill $supplierBill): void
    {
        Gate::authorize('view', $supplierBill);
        $this->supplierBill = $supplierBill->load(['supplier', 'purchaseOrder', 'items.product', 'items.purchaseOrderItem', 'items.virtualStockIntake']);
    }

    public function issue(): void
    {
        (new IssueSupplierBill)($this->supplierBill);
        $this->supplierBill->refresh()->load(['supplier', 'purchaseOrder', 'items.product', 'items.purchaseOrderItem', 'items.virtualStockIntake']);
    }

    public function void(): void
    {
        (new VoidSupplierBill)($this->supplierBill);
        $this->supplierBill->refresh();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'canUpdate' => Gate::check('update', $this->supplierBill),
            'canIssue' => Gate::check('issue', $this->supplierBill),
            'canVoid' => Gate::check('void', $this->supplierBill),
        ];
    }

    public function render(): View
    {
        return view('supplier-bills::show', $this->with());
    }
}
