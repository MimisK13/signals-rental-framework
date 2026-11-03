<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders\Presentation\Livewire;

use App\Domain\Products\Models\Product;
use App\Domain\PurchaseOrders\Actions\CreatePurchaseOrder;
use App\Domain\PurchaseOrders\Actions\UpdateDraftPurchaseOrder;
use App\Domain\PurchaseOrders\Data\CreatePurchaseOrderData;
use App\Domain\PurchaseOrders\Data\UpdateDraftPurchaseOrderData;
use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Store;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Form extends Component
{
    public ?int $purchaseOrderId = null;

    public int $supplierId = 0;

    public int $storeId = 0;

    public string $poType = 'purchase';

    public ?string $reference = null;

    public ?string $notes = null;

    /** @var list<array{id?: int, product_id?: int, description: string, quantity: string, unit_cost?: string}> */
    public array $lines = [];

    public function mount(?PurchaseOrder $purchaseOrder = null): void
    {
        if ($purchaseOrder?->exists) {
            Gate::authorize('update', $purchaseOrder);
            $purchaseOrder->ensureDraft();
            $purchaseOrder->load('items');

            $this->purchaseOrderId = $purchaseOrder->id;
            $this->supplierId = $purchaseOrder->supplier_id;
            $this->storeId = $purchaseOrder->store_id;
            $this->poType = $purchaseOrder->po_type;
            $this->reference = $purchaseOrder->reference;
            $this->notes = $purchaseOrder->notes;
            $this->lines = $purchaseOrder->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'unit_cost' => $item->formatMoneyCost('unit_cost'),
            ])->values()->all();
        } else {
            Gate::authorize('create', PurchaseOrder::class);
            $defaultStore = Store::query()->orderBy('id')->value('id');
            $this->storeId = (int) ($defaultStore ?? 0);
            $this->lines = [
                ['description' => '', 'quantity' => '1.00'],
            ];
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['description' => '', 'quantity' => '1.00'];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 1) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(): void
    {
        $this->validate([
            'supplierId' => ['required', 'integer', Rule::exists('members', 'id')->where('membership_type', MembershipType::Organisation->value)],
            'storeId' => ['required', 'integer', Rule::exists('stores', 'id')],
            'poType' => ['required', Rule::in(array_keys(config('purchase_orders.types', [])))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
        ]);

        $payloadLines = array_map(function (array $line): array {
            $row = [
                'description' => $line['description'],
                'quantity' => bcadd((string) $line['quantity'], '0', 2),
                'product_id' => ! empty($line['product_id']) ? (int) $line['product_id'] : null,
            ];

            if (isset($line['id'])) {
                $row['id'] = (int) $line['id'];
            }

            if (isset($line['unit_cost']) && $line['unit_cost'] !== '') {
                $row['unit_cost'] = (int) round(((float) $line['unit_cost']) * 100);
            }

            return $row;
        }, $this->lines);

        if ($this->purchaseOrderId !== null) {
            $purchaseOrder = PurchaseOrder::query()->findOrFail($this->purchaseOrderId);
            (new UpdateDraftPurchaseOrder)($purchaseOrder, UpdateDraftPurchaseOrderData::from([
                'supplier_id' => $this->supplierId,
                'store_id' => $this->storeId,
                'po_type' => $this->poType,
                'reference' => $this->reference,
                'notes' => $this->notes,
                'lines' => $payloadLines,
            ]));
            $this->redirect(route('purchase-orders.show', $purchaseOrder), navigate: true);

            return;
        }

        $result = (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
            'supplier_id' => $this->supplierId,
            'store_id' => $this->storeId,
            'po_type' => $this->poType,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'lines' => $payloadLines,
        ]));

        $this->redirect(route('purchase-orders.show', $result->id), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->purchaseOrderId !== null,
            'suppliers' => Member::query()->where('membership_type', MembershipType::Organisation->value)->orderBy('name')->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'poTypes' => config('purchase_orders.types', []),
        ];
    }

    public function render(): ViewContract
    {
        return view('purchase-orders::form', $this->with());
    }
}
