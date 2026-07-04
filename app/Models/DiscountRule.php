<?php

namespace App\Models;

use App\Enums\DiscountRuleScope;
use Database\Factories\DiscountRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $criteria
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 */
class DiscountRule extends Model
{
    /** @use HasFactory<DiscountRuleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'discount_category_id',
        'discount_percent',
        'criteria',
        'rule_scope',
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
            'discount_percent' => 'decimal:2',
            'criteria' => 'array',
            'rule_scope' => DiscountRuleScope::class,
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DiscountCategory, $this>
     */
    public function discountCategory(): BelongsTo
    {
        return $this->belongsTo(DiscountCategory::class);
    }
}
