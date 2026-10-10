<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\TaxResultType;
use App\Models\Traits\FormatsMoney;
use App\Services\SchemaBuilder;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Read-optimised projection of an invoice line item.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $opportunity_item_id
 * @property int|null $product_id
 * @property string $description
 * @property string $quantity
 * @property string|null $invoiced_quantity
 * @property int $unit_price
 * @property int $discount
 * @property int $tax
 * @property int $total
 * @property string $tax_treatment
 * @property int|null $product_tax_class_id
 * @property string $currency_code
 * @property string $exchange_rate
 * @property bool $prices_include_tax
 * @property int $sort_order
 */
class InvoiceItem extends Model implements HasSchema
{
    /** @use HasFactory<InvoiceItemFactory> */
    use FormatsMoney, HasFactory;

    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'invoice_id',
        'opportunity_item_id',
        'product_id',
        'description',
        'quantity',
        'invoiced_quantity',
        'unit_price',
        'discount',
        'tax',
        'total',
        'tax_treatment',
        'product_tax_class_id',
        'currency_code',
        'exchange_rate',
        'prices_include_tax',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'invoiced_quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'prices_include_tax' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function moneyFormattingCurrency(): string
    {
        return $this->currency_code ?: $this->baseFormattingCurrency();
    }

    public function taxTreatmentEnum(): TaxResultType
    {
        return TaxResultType::from($this->tax_treatment);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<OpportunityItem, $this>
     */
    public function opportunityItem(): BelongsTo
    {
        return $this->belongsTo(OpportunityItem::class);
    }

    /**
     * @return HasMany<CreditNoteItem, $this>
     */
    public function creditNoteItems(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductTaxClass, $this>
     */
    public function productTaxClass(): BelongsTo
    {
        return $this->belongsTo(ProductTaxClass::class);
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->relation('invoice_id')->label('Invoice')
            ->relation('invoice', 'belongsTo', Invoice::class, 'number')
            ->filterable()->sortable();
        $builder->relation('product_id')->label('Product')
            ->relation('product', 'belongsTo', Product::class, 'name')
            ->filterable();
        $builder->string('description')->label('Description')->searchable()->filterable();
        $builder->decimal('quantity')->label('Quantity')->sortable();
        $builder->decimal('invoiced_quantity')->label('Invoiced Qty')->sortable();
        $builder->currency('unit_price')->label('Unit Price')->sortable();
        $builder->currency('discount')->label('Discount')->sortable();
        $builder->currency('tax')->label('Tax')->sortable();
        $builder->currency('total')->label('Total')->sortable();
        $builder->enum('tax_treatment')->label('Tax Treatment')->filterable();
        $builder->string('currency_code')->label('Currency')->filterable();
    }
}
