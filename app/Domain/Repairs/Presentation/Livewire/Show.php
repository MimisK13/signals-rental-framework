<?php

declare(strict_types=1);

namespace App\Domain\Repairs\Presentation\Livewire;

use App\Domain\Repairs\Actions\CompleteRepair;
use App\Domain\Repairs\Actions\MarkRepairUnrepairable;
use App\Domain\Repairs\Actions\SetRepairUsability;
use App\Domain\Repairs\Actions\StartRepair;
use App\Domain\Repairs\Models\Repair;
use App\Support\Formatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Show extends Component
{
    public Repair $repair;

    public function mount(Repair $repair): void
    {
        Gate::authorize('view', $repair);
        $this->repair = $repair->load(['product', 'store', 'asset.currentStockLevel', 'reportedBy', 'assignedTo', 'externalRepairer', 'expense', 'supplierBillItems.supplierBill']);
    }

    public function start(): void
    {
        $this->repair = (new StartRepair)($this->repair);
    }

    public function complete(): void
    {
        $this->repair = (new CompleteRepair)($this->repair);
    }

    public function markUnrepairable(): void
    {
        $this->repair = (new MarkRepairUnrepairable)($this->repair);
    }

    public function setUsability(bool $isUsable): void
    {
        $this->repair = (new SetRepairUsability)($this->repair, $isUsable);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return ['formatter' => app(Formatter::class)];
    }

    public function render(): View
    {
        return view('repairs::show', $this->with());
    }
}
