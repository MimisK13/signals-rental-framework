<?php

declare(strict_types=1);

namespace App\Domain\SupplierBills\Presentation\Livewire;

use App\Domain\Members\Models\Member;
use App\Domain\Products\Models\Product;
use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\PurchaseOrders\Models\PurchaseOrderItem;
use App\Domain\Stores\Models\Store;
use App\Domain\SupplierBills\Actions\CreateSupplierBill;
use App\Domain\SupplierBills\Actions\UpdateDraftSupplierBill;
use App\Domain\SupplierBills\Models\SupplierBill;
use App\Domain\SupplierBills\Models\SupplierBillItem;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Form extends Component
{
    public ?int $supplierBillId = null;

    public int $supplierId = 0;

    public ?int $storeId = null;

    public ?int $purchaseOrderId = null;

    public string $documentNumber = '';

    public string $currencyCode = 'EUR';

    public ?string $dueAt = null;

    public ?string $reference = null;

    public ?string $notes = null;

    /** @var list<array<string, mixed>> */
    public array $lines = [];

    public function mount(?SupplierBill $supplierBill = null): void
    {
        if ($supplierBill?->exists) {
            Gate::authorize('update', $supplierBill);
            $this->supplierBillId = $supplierBill->id;
            $this->supplierId = $supplierBill->supplier_id;
            $this->storeId = $supplierBill->store_id;
            $this->purchaseOrderId = $supplierBill->purchase_order_id;
            $this->documentNumber = $supplierBill->document_number;
            $this->currencyCode = $supplierBill->currency_code;
            $this->dueAt = $supplierBill->due_at?->format('Y-m-d');
            $this->reference = $supplierBill->reference;
            $this->notes = $supplierBill->notes;
            $this->lines = $supplierBill->items->map(fn (SupplierBillItem $line): array => [
                'product_id' => $line->product_id,
                'purchase_order_item_id' => $line->purchase_order_item_id,
                'virtual_stock_intake_id' => $line->virtual_stock_intake_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_cost' => number_format($line->unit_cost / 100, 2, '.', ''),
                'tax' => number_format($line->tax / 100, 2, '.', ''),
            ])->values()->all();
        } else {
            Gate::authorize('create', SupplierBill::class);
            $this->lines = [['description' => '', 'quantity' => '1.00', 'unit_cost' => '0.00', 'tax' => '0.00', 'purchase_order_item_id' => null, 'virtual_stock_intake_id' => null]];
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['description' => '', 'quantity' => '1.00', 'unit_cost' => '0.00', 'tax' => '0.00', 'purchase_order_item_id' => null, 'virtual_stock_intake_id' => null];
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
            'supplierId' => ['required', 'integer', Rule::exists('members', 'id')],
            'storeId' => ['nullable', 'integer', Rule::exists('stores', 'id')],
            'purchaseOrderId' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')],
            'documentNumber' => ['required', 'string', 'max:100'],
            'dueAt' => ['nullable', 'date'],
            'currencyCode' => ['required', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.tax' => ['nullable', 'numeric', 'min:0'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'lines.*.purchase_order_item_id' => ['nullable', 'integer', Rule::exists('purchase_order_items', 'id')],
            'lines.*.virtual_stock_intake_id' => ['nullable', 'integer', Rule::exists('virtual_stock_intakes', 'id')],
        ]);

        $lines = array_map(fn (array $line): array => [
            'product_id' => filled($line['product_id'] ?? null) ? (int) $line['product_id'] : null,
            'purchase_order_item_id' => filled($line['purchase_order_item_id'] ?? null) ? (int) $line['purchase_order_item_id'] : null,
            'virtual_stock_intake_id' => filled($line['virtual_stock_intake_id'] ?? null) ? (int) $line['virtual_stock_intake_id'] : null,
            'description' => $line['description'],
            'quantity' => bcadd((string) $line['quantity'], '0', 2),
            'unit_cost' => (int) round((float) $line['unit_cost'] * 100),
            'tax' => (int) round((float) ($line['tax'] ?? 0) * 100),
        ], $this->lines);

        $attributes = [
            'supplier_id' => $this->supplierId,
            'store_id' => $this->storeId,
            'purchase_order_id' => $this->purchaseOrderId,
            'document_number' => $this->documentNumber,
            'due_at' => $this->dueAt ?: null,
            'currency_code' => strtoupper($this->currencyCode),
            'exchange_rate' => '1',
            'reference' => $this->reference,
            'notes' => $this->notes,
        ];

        $bill = $this->supplierBillId === null
            ? app(CreateSupplierBill::class)($attributes, $lines)
            : app(UpdateDraftSupplierBill::class)(SupplierBill::query()->findOrFail($this->supplierBillId), $attributes, $lines);

        $this->redirect(route('supplier-bills.show', $bill), navigate: true);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'suppliers' => Member::query()->where('membership_type', 'organisation')->orderBy('name')->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'purchaseOrders' => PurchaseOrder::query()->orderByDesc('id')->limit(200)->get(['id', 'number']),
            'purchaseOrderItems' => PurchaseOrderItem::query()->with('purchaseOrder')->orderByDesc('id')->limit(300)->get(),
            'virtualIntakes' => VirtualStockIntake::query()->with('product')->orderByDesc('id')->limit(300)->get(),
            'isEditing' => $this->supplierBillId !== null,
        ];
    }

    public function render(): View
    {
        return view('supplier-bills::form', $this->with());
    }
}
