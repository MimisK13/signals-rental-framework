<?php

declare(strict_types=1);

namespace App\Domain\SupplierBills\Models;

use App\Domain\PurchaseOrders\Models\PurchaseOrderItem;
use App\Domain\Repairs\Models\Repair;
use App\Domain\SupplierBills\Database\Factories\SupplierBillItemFactory;
use App\Models\Product;
use App\Models\VirtualStockIntake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_bill_id
 * @property int|null $product_id
 * @property int|null $purchase_order_item_id
 * @property int|null $virtual_stock_intake_id
 * @property int|null $repair_id
 * @property string $description
 * @property string $quantity
 * @property int $unit_cost
 * @property int $tax
 * @property int $total
 */
class SupplierBillItem extends Model
{
    /** @use HasFactory<SupplierBillItemFactory> */
    use HasFactory;

    protected static function newFactory(): SupplierBillItemFactory
    {
        return SupplierBillItemFactory::new();
    }

    protected $fillable = [
        'supplier_bill_id', 'product_id', 'purchase_order_item_id', 'virtual_stock_intake_id', 'repair_id',
        'description', 'quantity', 'unit_cost', 'tax', 'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
        ];
    }

    /** @return BelongsTo<SupplierBill, $this> */
    public function supplierBill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<PurchaseOrderItem, $this> */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /** @return BelongsTo<VirtualStockIntake, $this> */
    public function virtualStockIntake(): BelongsTo
    {
        return $this->belongsTo(VirtualStockIntake::class);
    }

    /** @return BelongsTo<Repair, $this> */
    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }

    public function netAmount(): int
    {
        return $this->total - $this->tax;
    }
}
