<?php

namespace App\Models;

use Database\Factories\SupplierBillItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBillItem extends Model
{
    /** @use HasFactory<SupplierBillItemFactory> */
    use HasFactory;

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
