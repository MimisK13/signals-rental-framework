<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders\Presentation\Livewire;

use App\Domain\PurchaseOrders\Actions\CancelPurchaseOrder;
use App\Domain\PurchaseOrders\Actions\DeleteDraftPurchaseOrder;
use App\Domain\PurchaseOrders\Actions\ReceivePurchaseOrder;
use App\Domain\PurchaseOrders\Actions\SendPurchaseOrder;
use App\Domain\PurchaseOrders\Data\ReceivePurchaseOrderData;
use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\PurchaseOrders\Services\PurchaseOrderStatusRegistry;
use App\Domain\VirtualStock\Actions\ReturnVirtualStockIntake;
use App\Domain\VirtualStock\Data\ReturnVirtualStockIntakeData;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use App\Domain\VirtualStock\Services\VirtualStockIntakeReturnGuard;
use App\Domain\VirtualStock\Services\VirtualStockStatusRegistry;
use App\Livewire\Concerns\HandlesVerbsStateErrors;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Show extends Component
{
    use HandlesVerbsStateErrors;

    public PurchaseOrder $purchaseOrder;

    public bool $showCancelModal = false;

    public bool $showReceiveModal = false;

    public bool $showReturnIntakeModal = false;

    public ?int $returnIntakeId = null;

    /** @var array<int, array{purchase_order_item_id: int, quantity: string}> */
    public array $receiveLines = [];

    public ?string $cancelReason = null;

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        Gate::authorize('view', $purchaseOrder);

        $this->purchaseOrder = $purchaseOrder->load(['supplier', 'store', 'opportunity', 'items.product', 'virtualStockIntakes.product', 'supplierBills.supplier']);
        $this->resetReceiveLines();
    }

    public function rendering(View $view): void
    {
        $view->title($this->purchaseOrder->number);
    }

    public function send(): void
    {
        $this->runVerbsTransition(
            fn () => (new SendPurchaseOrder)($this->purchaseOrder),
            fn () => $this->refreshPurchaseOrder(),
        );
    }

    public function cancel(): void
    {
        $this->runVerbsTransition(function (): void {
            (new CancelPurchaseOrder)($this->purchaseOrder, $this->cancelReason);
            $this->showCancelModal = false;
            $this->refreshPurchaseOrder();
        });
    }

    public function receive(): void
    {
        $lines = ReceivePurchaseOrderData::validateAndCreate([
            'lines' => array_values($this->receiveLines),
        ]);

        $this->runVerbsTransition(function () use ($lines): void {
            app(ReceivePurchaseOrder::class)($this->purchaseOrder, $lines);

            $this->showReceiveModal = false;
            $this->refreshPurchaseOrder();
            $this->resetReceiveLines();
        });
    }

    public function openReceiveModal(): void
    {
        $this->resetReceiveLines();
        $this->showReceiveModal = true;
    }

    private function resetReceiveLines(): void
    {
        $this->receiveLines = [];

        foreach ($this->purchaseOrder->items as $item) {
            $remaining = bcsub(
                (string) $item->quantity,
                bcadd((string) $item->received_quantity, '0', 2),
                2,
            );

            if (bccomp($remaining, '0', 2) <= 0) {
                continue;
            }

            $this->receiveLines[$item->id] = [
                'purchase_order_item_id' => $item->id,
                'quantity' => $remaining,
            ];
        }
    }

    public function deleteDraft(): void
    {
        $this->runVerbsTransition(
            fn () => (new DeleteDraftPurchaseOrder)($this->purchaseOrder),
            fn () => $this->redirect(route('purchase-orders.index'), navigate: true),
        );
    }

    public function openReturnIntakeModal(int $intakeId): void
    {
        $this->returnIntakeId = $intakeId;
        $this->showReturnIntakeModal = true;
    }

    public function returnIntakeToSupplier(bool $force = false): void
    {
        if ($this->returnIntakeId === null) {
            return;
        }

        $intake = VirtualStockIntake::query()->findOrFail($this->returnIntakeId);

        app(ReturnVirtualStockIntake::class)(
            $intake,
            new ReturnVirtualStockIntakeData(force: $force),
        );

        $this->showReturnIntakeModal = false;
        $this->returnIntakeId = null;
        $this->refreshPurchaseOrder();
    }

    public function confirmReturnIntakeToSupplier(): void
    {
        $this->returnIntakeToSupplier(force: true);
    }

    private function refreshPurchaseOrder(): void
    {
        $this->purchaseOrder->refresh()->load(['supplier', 'store', 'opportunity', 'items.product', 'virtualStockIntakes.product', 'supplierBills.supplier']);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = app(PurchaseOrderStatusRegistry::class);
        $virtualStockStatuses = app(VirtualStockStatusRegistry::class)->statuses();
        $returnIntake = $this->returnIntakeId !== null
            ? VirtualStockIntake::query()->find($this->returnIntakeId)
            : null;

        return [
            'statuses' => $registry->statuses(),
            'virtualStockStatuses' => $virtualStockStatuses,
            'canSend' => Gate::check('send', $this->purchaseOrder),
            'canReceive' => Gate::check('receive', $this->purchaseOrder),
            'canCancel' => Gate::check('cancel', $this->purchaseOrder)
                && $registry->canTransitionTo($this->purchaseOrder->status, 'cancelled'),
            'canUpdate' => Gate::check('update', $this->purchaseOrder),
            'canDelete' => Gate::check('delete', $this->purchaseOrder),
            'outstandingAllocations' => $returnIntake !== null
                ? app(VirtualStockIntakeReturnGuard::class)->outstandingAllocations($returnIntake)
                : [],
        ];
    }

    public function render(): ViewContract
    {
        return view('purchase-orders::show', $this->with());
    }
}
