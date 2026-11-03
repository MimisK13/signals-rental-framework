<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Crew\Database\Factories\CrewTimeEntryFactory;
use App\Domain\Crew\Enums\CrewTimeEntryKind;
use App\Domain\Crew\Enums\CrewTimesheetStatus;
use App\Domain\Stores\Scopes\StoreScope;
use App\Models\Traits\FormatsMoney;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $crew_timesheet_id
 * @property int|null $crew_shift_id
 * @property CrewTimeEntryKind $kind
 * @property Carbon $work_date
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property int $break_minutes
 * @property int $minutes
 * @property bool $is_overtime
 * @property string|null $multiplier
 * @property int|null $applied_rate_minor
 * @property int|null $amount_minor
 * @property string|null $currency_code
 * @property string $source
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CrewShift|null $shift
 * @property-read User|null $creator
 */
class CrewTimeEntry extends Model
{
    /** @use HasFactory<CrewTimeEntryFactory> */
    use FormatsMoney, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'crew_timesheet_id',
        'crew_shift_id',
        'kind',
        'work_date',
        'started_at',
        'ended_at',
        'break_minutes',
        'minutes',
        'is_overtime',
        'multiplier',
        'applied_rate_minor',
        'amount_minor',
        'currency_code',
        'source',
        'notes',
        'created_by',
    ];

    protected static function booted(): void
    {
        $guard = static function (self $entry): void {
            $ids = array_unique(array_filter([$entry->crew_timesheet_id, $entry->getRawOriginal('crew_timesheet_id')]));
            foreach ($ids as $id) {
                $status = CrewTimesheet::withoutGlobalScope(StoreScope::class)->whereKey($id)->value('status');
                if (in_array($status, [CrewTimesheetStatus::Approved, CrewTimesheetStatus::Locked], true)) {
                    throw ValidationException::withMessages(['entries' => 'Approved time entries are immutable.']);
                }
                if ($status === CrewTimesheetStatus::Submitted && (! $entry->exists || $entry->isDirty(['crew_timesheet_id', 'crew_shift_id', 'kind', 'work_date', 'started_at', 'ended_at', 'break_minutes', 'notes']))) {
                    throw ValidationException::withMessages(['entries' => 'Submitted time entries cannot be edited.']);
                }
            }
        };
        static::saving($guard);
        static::deleting(static function (self $entry) use ($guard): void {
            $guard($entry);
            $status = CrewTimesheet::withoutGlobalScope(StoreScope::class)->whereKey($entry->crew_timesheet_id)->value('status');
            if ($status === CrewTimesheetStatus::Submitted) {
                throw ValidationException::withMessages(['entries' => 'Submitted time entries cannot be deleted.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'kind' => CrewTimeEntryKind::class,
            'work_date' => 'date',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'break_minutes' => 'integer',
            'minutes' => 'integer',
            'is_overtime' => 'boolean',
            'multiplier' => 'decimal:2',
            'applied_rate_minor' => 'integer',
            'amount_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<CrewTimesheet, $this> */
    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(CrewTimesheet::class, 'crew_timesheet_id');
    }

    /** @return BelongsTo<CrewShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(CrewShift::class, 'crew_shift_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function moneyFormattingCurrency(): string
    {
        $code = $this->currency_code;

        if ($code !== null && $code !== '') {
            return $code;
        }

        $timesheetCurrency = $this->timesheet?->currency_code;

        return $timesheetCurrency !== null && $timesheetCurrency !== ''
            ? $timesheetCurrency
            : $this->baseFormattingCurrency();
    }
}
