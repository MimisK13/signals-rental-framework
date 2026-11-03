<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Models;

use App\Domain\Products\Models\Product;
use App\Domain\Quotations\Database\Factories\QuotationItemFactory;
use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Rates\Models\RateDefinition;
use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use App\Models\RevenueGroup;
use App\Models\Traits\FormatsMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $state_id
 * @property int $quotation_id
 * @property int $version_id
 * @property int|null $itemable_id
 * @property string|null $itemable_type
 * @property QuotationItemType $item_type
 * @property string $path
 * @property int|null $revenue_group_id
 * @property string $name
 * @property string|null $description
 * @property string $quantity
 * @property int $unit_price
 * @property int|null $rate_definition_id
 * @property ChargePeriod $charge_period
 * @property int $total
 * @property int $tax_total
 * @property int $gross_total
 * @property string|null $currency_code
 * @property string|null $discount_percent
 * @property string|null $deal_price_discount_percent
 * @property int|null $deal_price
 * @property int|null $deal_price_original_total
 * @property int|null $deal_price_original_tax_total
 * @property int|null $deal_price_original_gross_total
 * @property bool $is_discount_manual
 * @property string|null $tax_rate
 * @property LineItemTransactionType $transaction_type
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_optional
 * @property array<string, mixed>|null $custom_fields
 * @property array<string, mixed>|null $pricing_breakdown
 * @property string|null $notes
 */
final class QuotationItem extends Model
{
    /** @use HasFactory<QuotationItemFactory> */
    use FormatsMoney, HasFactory;

    public $incrementing = false;

    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'quotation_id',
        'version_id',
        'itemable_id',
        'itemable_type',
        'item_type',
        'path',
        'revenue_group_id',
        'name',
        'description',
        'quantity',
        'unit_price',
        'rate_definition_id',
        'charge_period',
        'total',
        'tax_total',
        'gross_total',
        'currency_code',
        'discount_percent',
        'deal_price_discount_percent',
        'deal_price',
        'deal_price_original_total',
        'deal_price_original_tax_total',
        'deal_price_original_gross_total',
        'is_discount_manual',
        'tax_rate',
        'transaction_type',
        'starts_at',
        'ends_at',
        'is_optional',
        'custom_fields',
        'pricing_breakdown',
        'notes',
    ];

    protected static function newFactory(): QuotationItemFactory
    {
        return QuotationItemFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'item_type' => QuotationItemType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'rate_definition_id' => 'integer',
            'charge_period' => ChargePeriod::class,
            'total' => 'integer',
            'tax_total' => 'integer',
            'gross_total' => 'integer',
            'discount_percent' => 'decimal:2',
            'deal_price_discount_percent' => 'decimal:10',
            'deal_price' => 'integer',
            'deal_price_original_total' => 'integer',
            'deal_price_original_tax_total' => 'integer',
            'deal_price_original_gross_total' => 'integer',
            'is_discount_manual' => 'boolean',
            'tax_rate' => 'decimal:2',
            'transaction_type' => LineItemTransactionType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_optional' => 'boolean',
            'custom_fields' => 'array',
            'pricing_breakdown' => 'array',
        ];
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'version_id');
    }

    /** @return MorphTo<Model, $this> */
    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<RevenueGroup, $this> */
    public function revenueGroup(): BelongsTo
    {
        return $this->belongsTo(RevenueGroup::class);
    }

    /** @return BelongsTo<RateDefinition, $this> */
    public function rateDefinition(): BelongsTo
    {
        return $this->belongsTo(RateDefinition::class);
    }

    public function depth(): int
    {
        return max(1, intdiv(strlen($this->path), 4));
    }

    public function parentPath(): ?string
    {
        return strlen($this->path) > 4 ? substr($this->path, 0, -4) : null;
    }

    public function isProductBacked(): bool
    {
        return $this->itemable_id !== null
            && ($this->itemable_type === Product::class || $this->itemable_type === 'Product');
    }
}
