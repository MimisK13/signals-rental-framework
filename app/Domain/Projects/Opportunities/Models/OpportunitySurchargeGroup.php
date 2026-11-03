<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Models;

use App\Domain\Projects\Opportunities\Database\Factories\OpportunitySurchargeGroupFactory;
use App\Models\SurchargeGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $opportunity_id
 * @property int $surcharge_group_id
 * @property bool $is_active
 */
class OpportunitySurchargeGroup extends Model
{
    /** @use HasFactory<OpportunitySurchargeGroupFactory> */
    use HasFactory;

    protected static function newFactory(): OpportunitySurchargeGroupFactory
    {
        return OpportunitySurchargeGroupFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'opportunity_id',
        'surcharge_group_id',
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
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * @return BelongsTo<SurchargeGroup, $this>
     */
    public function surchargeGroup(): BelongsTo
    {
        return $this->belongsTo(SurchargeGroup::class);
    }
}
