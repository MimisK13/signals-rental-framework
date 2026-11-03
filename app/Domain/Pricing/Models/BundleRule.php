<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use App\Domain\Pricing\Database\Factories\BundleRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $bundle_percent
 * @property int|null $bundle_fixed_price_minor
 * @property array<string, mixed>|null $criteria
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class BundleRule extends Model
{
    /** @use HasFactory<BundleRuleFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): BundleRuleFactory
    {
        return BundleRuleFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'bundle_percent',
        'bundle_fixed_price_minor',
        'criteria',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bundle_percent' => 'decimal:2',
            'bundle_fixed_price_minor' => 'integer',
            'criteria' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isPercent(): bool
    {
        return $this->bundle_percent !== null;
    }

    public function isFixed(): bool
    {
        return $this->bundle_fixed_price_minor !== null;
    }
}
