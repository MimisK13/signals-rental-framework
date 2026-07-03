<?php

namespace App\Models;

use Database\Factories\SurchargeGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurchargeGroup extends Model
{
    /** @use HasFactory<SurchargeGroupFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'is_default',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Surcharge, $this>
     */
    public function surcharges(): HasMany
    {
        return $this->hasMany(Surcharge::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<OpportunitySurchargeGroup, $this>
     */
    public function opportunityGroups(): HasMany
    {
        return $this->hasMany(OpportunitySurchargeGroup::class);
    }
}
