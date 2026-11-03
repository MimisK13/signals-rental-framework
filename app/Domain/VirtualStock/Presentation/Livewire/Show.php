<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock\Presentation\Livewire;

use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Availability\Shortages\Services\CostApportionmentContextBuilder;
use App\Domain\Availability\Shortages\Services\CostApportionmentRegistry;
use App\Domain\VirtualStock\Actions\CancelVirtualStockIntake;
use App\Domain\VirtualStock\Actions\ConfirmVirtualStockIntake;
use App\Domain\VirtualStock\Actions\DeleteDraftVirtualStockIntake;
use App\Domain\VirtualStock\Actions\ReceiveVirtualStockIntake;
use App\Domain\VirtualStock\Actions\ReturnVirtualStockIntake;
use App\Domain\VirtualStock\Data\ReturnVirtualStockIntakeData;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use App\Domain\VirtualStock\Services\VirtualStockIntakeProfitAndLossBuilder;
use App\Domain\VirtualStock\Services\VirtualStockIntakeReturnGuard;
use App\Domain\VirtualStock\Services\VirtualStockStatusRegistry;
use App\Livewire\Concerns\HandlesVerbsStateErrors;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class Show extends Component
{
    use HandlesVerbsStateErrors;

    public VirtualStockIntake $virtualStockIntake;

    public bool $showReturnConfirmModal = false;

    public function mount(VirtualStockIntake $virtualStockIntake): void
    {
        Gate::authorize('view', $virtualStockIntake);
        $this->virtualStockIntake = $virtualStockIntake->load(['product', 'store', 'purchaseOrder', 'purchaseOrderItem', 'virtualAssets']);
    }

    public function confirm(): void
    {
        $this->runVerbsTransition(
            fn () => app(ConfirmVirtualStockIntake::class)($this->virtualStockIntake),
            function (): void {
                $this->virtualStockIntake->refresh()->load(['product', 'store']);
            },
        );
    }

    public function receive(): void
    {
        $this->runVerbsTransition(
            fn () => app(ReceiveVirtualStockIntake::class)($this->virtualStockIntake),
            function (): void {
                $this->virtualStockIntake->refresh();
            },
        );
    }

    public function openReturnToSupplier(): void
    {
        if (app(VirtualStockIntakeReturnGuard::class)->hasOutstandingAllocations($this->virtualStockIntake)) {
            $this->showReturnConfirmModal = true;

            return;
        }

        $this->returnToSupplier(force: false);
    }

    public function confirmReturnToSupplier(): void
    {
        $this->returnToSupplier(force: true);
        $this->showReturnConfirmModal = false;
    }

    public function returnToSupplier(bool $force = false): void
    {
        $this->runVerbsTransition(
            fn () => app(ReturnVirtualStockIntake::class)(
                $this->virtualStockIntake,
                new ReturnVirtualStockIntakeData(force: $force),
            ),
            function (): void {
                $this->virtualStockIntake->refresh();
            },
        );
    }

    public function cancel(): void
    {
        $this->runVerbsTransition(
            fn () => app(CancelVirtualStockIntake::class)($this->virtualStockIntake),
            function (): void {
                $this->virtualStockIntake->refresh();
            },
        );
    }

    public function deleteDraft(): void
    {
        $this->runVerbsTransition(
            fn () => (new DeleteDraftVirtualStockIntake)($this->virtualStockIntake),
            fn () => $this->redirect(route('virtual-stock.index'), navigate: true),
        );
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $statusRegistry = app(VirtualStockStatusRegistry::class);
        $canViewCosts = Gate::check('costs.view');
        $strategyKey = $this->resolveApportionmentStrategy();
        $apportionmentRegistry = app(CostApportionmentRegistry::class);
        $strategyName = $canViewCosts
            ? ($apportionmentRegistry->has($strategyKey)
                ? $apportionmentRegistry->get($strategyKey)->name()
                : $strategyKey)
            : null;

        $profitAndLoss = $canViewCosts
            ? app(VirtualStockIntakeProfitAndLossBuilder::class)->build(
                $this->virtualStockIntake,
                $strategyKey,
                $strategyName,
            )
            : null;

        $manualMismatchWarning = false;

        if ($canViewCosts && $this->virtualStockIntake->status !== 'draft') {
            if ($apportionmentRegistry->has($strategyKey) && $apportionmentRegistry->get($strategyKey)->requiresManualInput()) {
                $context = app(CostApportionmentContextBuilder::class)->build($this->virtualStockIntake);
                $allocations = $apportionmentRegistry->get($strategyKey)->calculate($context);
                $manualMismatchWarning = array_sum($allocations) !== $context->totalCostMinor;
            }
        }

        return [
            'statuses' => $statusRegistry->statuses(),
            'canConfirm' => Gate::check('confirm', $this->virtualStockIntake),
            'canReceive' => Gate::check('receive', $this->virtualStockIntake),
            'canReturn' => Gate::check('return', $this->virtualStockIntake),
            'canCancel' => Gate::check('cancel', $this->virtualStockIntake),
            'canUpdate' => Gate::check('update', $this->virtualStockIntake),
            'canDelete' => Gate::check('delete', $this->virtualStockIntake),
            'canViewCosts' => $canViewCosts,
            'apportionmentStrategy' => $strategyName,
            'profitAndLoss' => $profitAndLoss,
            'manualMismatchWarning' => $manualMismatchWarning,
            'outstandingAllocations' => app(VirtualStockIntakeReturnGuard::class)
                ->outstandingAllocations($this->virtualStockIntake),
            'isLateReturn' => $this->virtualStockIntake->isLateReturn(),
            'allocationsCount' => $this->virtualStockIntake->allocationsCount(),
            'intakeTotalMinor' => (int) $this->virtualStockIntake->unit_cost * (int) $this->virtualStockIntake->quantity,
        ];
    }

    private function resolveApportionmentStrategy(): string
    {
        if (is_string($this->virtualStockIntake->cost_apportionment_strategy)
            && $this->virtualStockIntake->cost_apportionment_strategy !== '') {
            return $this->virtualStockIntake->cost_apportionment_strategy;
        }

        $resolution = ShortageResolution::query()
            ->where('virtual_stock_intake_id', $this->virtualStockIntake->id)
            ->first();

        $metadataStrategy = $resolution?->metadata['apportionment_strategy'] ?? null;

        if (is_string($metadataStrategy) && $metadataStrategy !== '') {
            return $metadataStrategy;
        }

        return (string) settings(
            'shortages.cost_apportionment_strategy',
            config('shortages.cost_apportionment_strategy', 'primary_job'),
        );
    }

    public function render(): View
    {
        return view('virtual-stock::show', $this->with());
    }
}
