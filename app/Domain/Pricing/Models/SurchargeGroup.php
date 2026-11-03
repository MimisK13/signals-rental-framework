<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use App\Domain\Pricing\Database\Factories\SurchargeGroupFactory;
use App\Domain\Projects\Opportunities\Models\OpportunitySurchargeGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class SurchargeGroup extends Model
{
    /** @use HasFactory<SurchargeGroupFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): SurchargeGroupFactory
    {
        return SurchargeGroupFactory::new();
    }

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
