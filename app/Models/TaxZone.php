<?php

namespace App\Models;

use Database\Factories\TaxZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxZone extends Model
{
    /** @use HasFactory<TaxZoneFactory> */
    use HasFactory;

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
