<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use App\Domain\Pricing\Database\Factories\DiscountRuleFactory;
use App\Domain\Pricing\Enums\DiscountRuleScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $discount_category_id
 * @property string $discount_percent
 * @property array<string, mixed>|null $criteria
 * @property DiscountRuleScope $rule_scope
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DiscountRule extends Model
{
    /** @use HasFactory<DiscountRuleFactory> */
    use HasFactory;

    protected static function newFactory(): DiscountRuleFactory
    {
        return DiscountRuleFactory::new();
    }

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
