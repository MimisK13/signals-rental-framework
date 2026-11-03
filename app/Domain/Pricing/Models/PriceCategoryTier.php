<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use App\Domain\Pricing\Database\Factories\PriceCategoryTierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $price_category_id
 * @property int $tier_number
 * @property string|null $label
 * @property string $multiplier
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PriceCategoryTier extends Model
{
    /** @use HasFactory<PriceCategoryTierFactory> */
    use HasFactory;

    protected static function newFactory(): PriceCategoryTierFactory
    {
        return PriceCategoryTierFactory::new();
    }

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
