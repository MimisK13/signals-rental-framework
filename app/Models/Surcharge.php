<?php

namespace App\Models;

use Database\Factories\SurchargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Surcharge extends Model
{
    /** @use HasFactory<SurchargeFactory> */
    use HasFactory;

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
