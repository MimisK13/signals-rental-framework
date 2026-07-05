<?php

namespace App\Models;

use Database\Factories\VirtualStockCostOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-opportunity unit cost override for a virtual stock intake (P45 seam).
 *
 * @property int $id
 * @property int $virtual_stock_intake_id
 * @property int $opportunity_id
 * @property int $unit_cost_override
 * @property int|null $created_by
 */
class VirtualStockCostOverride extends Model
{
    /** @use HasFactory<VirtualStockCostOverrideFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'virtual_stock_intake_id',
        'opportunity_id',
        'unit_cost_override',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_cost_override' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<VirtualStockIntake, $this>
     */
    public function virtualStockIntake(): BelongsTo
    {
        return $this->belongsTo(VirtualStockIntake::class);
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
