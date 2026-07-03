<?php

namespace App\Models;

use Database\Factories\PriceCategoryTierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceCategoryTier extends Model
{
    /** @use HasFactory<PriceCategoryTierFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'price_category_id',
        'tier_number',
        'label',
        'multiplier',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tier_number' => 'integer',
            'multiplier' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PriceCategory, $this>
     */
    public function priceCategory(): BelongsTo
    {
        return $this->belongsTo(PriceCategory::class);
    }
}
