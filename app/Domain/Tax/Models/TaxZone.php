<?php

declare(strict_types=1);

namespace App\Domain\Tax\Models;

use App\Domain\Tax\Database\Factories\TaxZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $priority
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class TaxZone extends Model
{
    /** @use HasFactory<TaxZoneFactory> */
    use HasFactory;

    protected static function newFactory(): TaxZoneFactory
    {
        return TaxZoneFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'priority',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TaxZoneMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(TaxZoneMember::class);
    }

    /**
     * @return HasMany<TaxRule, $this>
     */
    public function sellerRules(): HasMany
    {
        return $this->hasMany(TaxRule::class, 'seller_zone_id');
    }

    /**
     * @return HasMany<TaxRule, $this>
     */
    public function buyerRules(): HasMany
    {
        return $this->hasMany(TaxRule::class, 'buyer_zone_id');
    }
}
