<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use App\Domain\Pricing\Database\Factories\SurchargeFactory;
use App\Domain\Projects\Opportunities\Models\OpportunityItemSurcharge;
use App\Domain\Tax\Models\ProductTaxClass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $surcharge_group_id
 * @property string $name
 * @property string|null $percent_value
 * @property int|null $fixed_value_minor
 * @property array<string, mixed>|null $criteria
 * @property bool $is_default
 * @property int|null $product_tax_class_id
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Surcharge extends Model
{
    /** @use HasFactory<SurchargeFactory> */
    use HasFactory;

    protected static function newFactory(): SurchargeFactory
    {
        return SurchargeFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'surcharge_group_id',
        'name',
        'percent_value',
        'fixed_value_minor',
        'criteria',
        'is_default',
        'product_tax_class_id',
        'valid_from',
        'valid_to',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent_value' => 'decimal:2',
            'fixed_value_minor' => 'integer',
            'criteria' => 'array',
            'is_default' => 'boolean',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function isPercent(): bool
    {
        return $this->percent_value !== null;
    }

    public function isFixed(): bool
    {
        return $this->fixed_value_minor !== null;
    }

    public function typeValue(): string
    {
        return $this->isPercent() ? 'percent' : 'fixed';
    }

    /**
     * Snapshot attributes captured when a surcharge is applied to a line item.
     *
     * @return array{
     *     name: string,
     *     surcharge_type: string,
     *     percent_value: string|null,
     *     fixed_value_minor: int|null
     * }
     */
    public function snapshotAttributes(): array
    {
        return [
            'name' => $this->name,
            'surcharge_type' => $this->typeValue(),
            'percent_value' => $this->percent_value !== null ? (string) $this->percent_value : null,
            'fixed_value_minor' => $this->fixed_value_minor,
        ];
    }

    /**
     * @return BelongsTo<SurchargeGroup, $this>
     */
    public function surchargeGroup(): BelongsTo
    {
        return $this->belongsTo(SurchargeGroup::class);
    }

    /**
     * @return BelongsTo<ProductTaxClass, $this>
     */
    public function productTaxClass(): BelongsTo
    {
        return $this->belongsTo(ProductTaxClass::class);
    }

    /**
     * @return HasMany<OpportunityItemSurcharge, $this>
     */
    public function itemSurcharges(): HasMany
    {
        return $this->hasMany(OpportunityItemSurcharge::class);
    }
}
