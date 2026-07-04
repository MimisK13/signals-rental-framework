<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Models\Traits\FormatsMoney;
use App\Services\SchemaBuilder;
use Database\Factories\ProductSupplierCostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $metadata
 */
class ProductSupplierCost extends Model implements HasSchema
{
    /** @use HasFactory<ProductSupplierCostFactory> */
    use FormatsMoney, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'supplier_id',
        'store_id',
        'cost',
        'currency_code',
        'cost_basis',
        'supplier_sku',
        'lead_time_days',
        'min_order_quantity',
        'is_preferred',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'integer',
            'lead_time_days' => 'integer',
            'min_order_quantity' => 'decimal:2',
            'is_preferred' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->relation('product_id')->label('Product')
            ->relation('product', 'belongsTo', Product::class, 'name')
            ->required()->filterable()->sortable();
        $builder->relation('supplier_id')->label('Supplier')
            ->relation('supplier', 'belongsTo', Member::class, 'name')
            ->required()->filterable()->sortable();
        $builder->relation('store_id')->label('Store')
            ->relation('store', 'belongsTo', Store::class, 'name')
            ->filterable()->sortable();
        $builder->currency('cost')->label('Cost')->required()->sortable();
        $builder->string('currency_code')->label('Currency')->required()->filterable();
        $builder->string('cost_basis')->label('Cost Basis')->required()->filterable()->sortable();
        $builder->string('supplier_sku')->label('Supplier SKU')->filterable();
        $builder->integer('lead_time_days')->label('Lead Time (days)')->filterable()->sortable();
        $builder->decimal('min_order_quantity')->label('Min Order Qty')->sortable();
        $builder->boolean('is_preferred')->label('Preferred')->filterable()->groupable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePreferred(Builder $query): Builder
    {
        return $query->where('is_preferred', true);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'supplier_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected function moneyFormattingCurrency(): string
    {
        $code = $this->currency_code;

        return $code !== '' ? $code : $this->baseFormattingCurrency();
    }
}
