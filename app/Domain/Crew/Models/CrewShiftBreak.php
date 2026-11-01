<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Crew\Database\Factories\CrewShiftBreakFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Phase 2 shape. MVP uses `crew_shifts.break_minutes`; this model exists so the
 * relation and migration land without a future breaking change.
 *
 * @property int $id
 * @property int $crew_shift_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_paid
 * @property string|null $label
 */
class CrewShiftBreak extends Model
{
    /** @use HasFactory<CrewShiftBreakFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'crew_shift_id',
        'starts_at',
        'ends_at',
        'is_paid',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_paid' => 'boolean',
        ];
    }

    /** @return BelongsTo<CrewShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(CrewShift::class, 'crew_shift_id');
    }
}
