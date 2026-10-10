<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Models\Traits\FormatsMoney;
use App\Services\SchemaBuilder;
use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $product_id
 * @property string $description
 * @property string $quantity
 * @property string $received_quantity
 * @property int $unit_cost
 * @property int $tax
 * @property int|null $product_tax_class_id
 * @property int $total
 * @property int|null $supplier_cost_id
 * @property int $sort_order
 */
class PurchaseOrderItem extends Model implements HasSchema
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use FormatsMoney, HasFactory;

    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'purchase_order_id',
        'product_id',
        'description',
        'quantity',
        'received_quantity',
        'unit_cost',
        'tax',
        'product_tax_class_id',
        'total',
        'supplier_cost_id',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_cost' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'quantity' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    protected function moneyFormattingCurrency(): string
    {
        return $this->purchaseOrder?->currency_code ?: $this->baseFormattingCurrency();
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->relation('purchase_order_id')->label('Purchase Order')
            ->relation('purchaseOrder', 'belongsTo', PurchaseOrder::class, 'number')
            ->filterable()->sortable();
        $builder->relation('product_id')->label('Product')
            ->relation('product', 'belongsTo', Product::class, 'name')
            ->filterable();
        $builder->string('description')->label('Description')->searchable()->filterable();
        $builder->decimal('quantity')->label('Quantity')->sortable();
        $builder->decimal('received_quantity')->label('Received Qty')->sortable();
        $builder->currency('unit_cost')->label('Unit Cost')->sortable();
        $builder->currency('tax')->label('Tax')->sortable();
        $builder->currency('total')->label('Total')->sortable();
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductSupplierCost, $this>
     */
    public function supplierCost(): BelongsTo
    {
        return $this->belongsTo(ProductSupplierCost::class, 'supplier_cost_id');
    }

    /**
     * @return HasMany<SupplierBillItem, $this>
     */
    public function supplierBillItems(): HasMany
    {
        return $this->hasMany(SupplierBillItem::class);
    }
}
