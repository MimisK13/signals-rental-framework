<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Administration\Models\User;
use App\Domain\Attachments\Concerns\HasAttachments;
use App\Domain\Crew\Database\Factories\CrewTimesheetFactory;
use App\Domain\Crew\Enums\CrewTimesheetStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Stores\Concerns\HasStoreScoping;
use App\Domain\Stores\Models\Store;
use App\Models\Traits\FormatsMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Approved hours for one assignment and period. Approval freezes the totals;
 * approval is explicitly not payment, which remains a Finance concern.
 *
 * @property int $id
 * @property int $crew_assignment_id
 * @property int $crew_member_id
 * @property int $project_id
 * @property int $store_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property CrewTimesheetStatus $status
 * @property int $total_minutes
 * @property int $total_pay_minor
 * @property string $currency_code
 * @property string|null $rounding_rule
 * @property array<array-key, mixed>|null $overtime_breakdown
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $rejected_at
 * @property int|null $rejected_by
 * @property string|null $rejection_reason
 * @property Carbon|null $locked_at
 * @property Carbon|null $exported_to_finance_at
 * @property string|null $finance_reference
 * @property int $version
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $submittedBy
 * @property-read User|null $approvedBy
 * @property-read User|null $rejectedBy
 * @property-read User|null $creator
 */
class CrewTimesheet extends Model
{
    /** @use HasFactory<CrewTimesheetFactory> */
    use FormatsMoney, HasAttachments, HasFactory, HasStoreScoping, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'crew_assignment_id',
        'crew_member_id',
        'project_id',
        'store_id',
        'period_start',
        'period_end',
        'status',
        'total_minutes',
        'total_pay_minor',
        'currency_code',
        'rounding_rule',
        'overtime_breakdown',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'locked_at',
        'exported_to_finance_at',
        'finance_reference',
        'version',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(static function (self $sheet): void {
            if (in_array($sheet->getRawOriginal('status'), ['approved', 'locked'], true) && $sheet->isDirty([
                'crew_assignment_id', 'crew_member_id', 'project_id', 'store_id', 'period_start', 'period_end',
                'total_minutes', 'total_pay_minor', 'currency_code', 'rounding_rule', 'overtime_breakdown',
                'approved_at', 'approved_by',
            ])) {
                throw ValidationException::withMessages(['timesheet' => 'Approved timesheet totals and identity are immutable.']);
            }
            if ($sheet->getRawOriginal('status') === 'approved' && $sheet->isDirty('status') && $sheet->status !== CrewTimesheetStatus::Locked) {
                throw ValidationException::withMessages(['status' => 'An approved timesheet can only be locked.']);
            }
            if ($sheet->getRawOriginal('status') === 'locked' && $sheet->isDirty('status')) {
                throw ValidationException::withMessages(['status' => 'A locked timesheet cannot be reopened.']);
            }
        });
        static::deleting(static function (self $sheet): void {
            if (in_array($sheet->status, [CrewTimesheetStatus::Approved, CrewTimesheetStatus::Locked], true)) {
                throw ValidationException::withMessages(['timesheet' => 'Approved timesheets must be retained.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => CrewTimesheetStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'total_minutes' => 'integer',
            'total_pay_minor' => 'integer',
            'overtime_breakdown' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'locked_at' => 'datetime',
            'exported_to_finance_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<CrewAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CrewAssignment::class, 'crew_assignment_id');
    }

    /** @return BelongsTo<CrewMember, $this> */
    public function crewMember(): BelongsTo
    {
        return $this->belongsTo(CrewMember::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<CrewTimeEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(CrewTimeEntry::class)->orderBy('work_date')->orderBy('started_at');
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function moneyFormattingCurrency(): string
    {
        $code = $this->currency_code;

        return $code !== '' ? $code : $this->baseFormattingCurrency();
    }
}
