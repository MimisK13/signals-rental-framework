<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use App\Domain\Members\Models\Member;
use App\Domain\Pricing\Database\Factories\PriceCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PriceCategory extends Model
{
    /** @use HasFactory<PriceCategoryFactory> */
    use HasFactory;

    protected static function newFactory(): PriceCategoryFactory
    {
        return PriceCategoryFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PriceCategoryTier, $this>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(PriceCategoryTier::class)->orderBy('sort_order')->orderBy('tier_number');
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
