<?php

namespace App\Models;

use Database\Factories\OpportunityItemSurchargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityItemSurcharge extends Model
{
    /** @use HasFactory<OpportunityItemSurchargeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'opportunity_item_id',
        'surcharge_id',
        'amount',
        'product_tax_class_id',
        'is_manual_override',
        'is_disabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_manual_override' => 'boolean',
            'is_disabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<OpportunityItem, $this>
     */
    public function opportunityItem(): BelongsTo
    {
        return $this->belongsTo(OpportunityItem::class);
    }

    /**
     * @return BelongsTo<Surcharge, $this>
     */
    public function surcharge(): BelongsTo
    {
        return $this->belongsTo(Surcharge::class);
    }

    /**
     * @return BelongsTo<ProductTaxClass, $this>
     */
    public function productTaxClass(): BelongsTo
    {
        return $this->belongsTo(ProductTaxClass::class);
    }
}
