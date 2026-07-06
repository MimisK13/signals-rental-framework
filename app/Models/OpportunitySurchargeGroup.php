<?php

namespace App\Models;

use Database\Factories\OpportunitySurchargeGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunitySurchargeGroup extends Model
{
    /** @use HasFactory<OpportunitySurchargeGroupFactory> */
    use HasFactory;

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
