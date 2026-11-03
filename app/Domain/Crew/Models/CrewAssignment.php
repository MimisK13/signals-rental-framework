<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Administration\Models\User;
use App\Domain\Attachments\Concerns\HasAttachments;
use App\Domain\Crew\Database\Factories\CrewAssignmentFactory;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewRateType;
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
 * The compensation agreement between the company and one crew member for one
 * request line. Confirmed snapshots are immutable; renegotiation creates a new
 * assignment rather than editing this one.
 *
 * @property int $id
 * @property int $crew_request_id
 * @property int $crew_request_line_id
 * @property int $crew_member_id
 * @property int $project_id
 * @property int $store_id
 * @property CrewAssignmentStatus $status
 * @property string|null $active_assignment_key
 * @property int|null $rate_card_id
 * @property CrewRateType $rate_type
 * @property int $rate_amount_minor
 * @property string $currency_code
 * @property string $exchange_rate_to_base
 * @property string $overtime_multiplier
 * @property string|null $min_call_hours
 * @property string|null $daily_call_hours
 * @property array<array-key, mixed>|null $allowances_snapshot
 * @property int|null $cost_estimate_minor
 * @property array<array-key, mixed>|null $cost_estimate_breakdown
 * @property Carbon|null $agreed_at
 * @property int|null $agreed_by
 * @property Carbon|null $invited_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property int|null $substitute_for_assignment_id
 * @property bool $requires_realignment
 * @property Carbon|null $realignment_flagged_at
 * @property string|null $realignment_reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CrewRateCard|null $rateCard
 * @property-read User|null $agreedBy
 * @property-read self|null $substituteFor
 * @property-read User|null $creator
 */
class CrewAssignment extends Model
{
    /** @use HasFactory<CrewAssignmentFactory> */
    use FormatsMoney, HasAttachments, HasFactory, HasStoreScoping, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'crew_request_id',
        'crew_request_line_id',
        'crew_member_id',
        'project_id',
        'store_id',
        'status',
        'active_assignment_key',
        'rate_card_id',
        'rate_type',
        'rate_amount_minor',
        'currency_code',
        'exchange_rate_to_base',
        'overtime_multiplier',
        'min_call_hours',
        'daily_call_hours',
        'allowances_snapshot',
        'cost_estimate_minor',
        'cost_estimate_breakdown',
        'agreed_at',
        'agreed_by',
        'invited_at',
        'responded_at',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
        'substitute_for_assignment_id',
        'requires_realignment',
        'realignment_flagged_at',
        'realignment_reason',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $assignment): void {
            $previousBreakdown = $assignment->getOriginal('cost_estimate_breakdown');
            if (is_array($previousBreakdown) && isset($previousBreakdown['cancellation'])) {
                $cancellation = $assignment->cost_estimate_breakdown['cancellation'] ?? null;
                if ($cancellation !== $previousBreakdown['cancellation'] || $assignment->isDirty('cost_estimate_minor')) {
                    throw ValidationException::withMessages([
                        'assignment' => 'Recorded cancellation compensation is immutable.',
                    ]);
                }
            }
            if (($assignment->getRawOriginal('agreed_at') !== null || $assignment->getRawOriginal('confirmed_at') !== null) && $assignment->isDirty([
                'crew_member_id', 'crew_request_line_id', 'crew_request_id', 'project_id', 'store_id',
                'rate_card_id', 'rate_type', 'rate_amount_minor', 'currency_code',
                'exchange_rate_to_base', 'overtime_multiplier', 'min_call_hours',
                'daily_call_hours', 'allowances_snapshot', 'agreed_at', 'agreed_by',
            ])) {
                throw ValidationException::withMessages([
                    'assignment' => 'Agreed compensation and assignment identity are immutable; create a replacement assignment.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => CrewAssignmentStatus::class,
            'rate_type' => CrewRateType::class,
            'rate_amount_minor' => 'integer',
            'exchange_rate_to_base' => 'decimal:6',
            'overtime_multiplier' => 'decimal:2',
            'min_call_hours' => 'decimal:2',
            'daily_call_hours' => 'decimal:2',
            'allowances_snapshot' => 'array',
            'cost_estimate_minor' => 'integer',
            'cost_estimate_breakdown' => 'array',
            'agreed_at' => 'datetime',
            'invited_at' => 'datetime',
            'responded_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'requires_realignment' => 'boolean',
            'realignment_flagged_at' => 'datetime',
        ];
    }

    /**
     * The portable unique key that prevents duplicate active assignments for the
     * same request line and member.
     */
    public static function activeKeyFor(int $crewRequestLineId, int $crewMemberId): string
    {
        return "line:{$crewRequestLineId}:member:{$crewMemberId}";
    }

    /** @return BelongsTo<CrewRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(CrewRequest::class, 'crew_request_id');
    }

    /** @return BelongsTo<CrewRequestLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(CrewRequestLine::class, 'crew_request_line_id');
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

    /** @return BelongsTo<CrewRateCard, $this> */
    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(CrewRateCard::class, 'rate_card_id');
    }

    /** @return BelongsTo<User, $this> */
    public function agreedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agreed_by');
    }

    /** @return BelongsTo<CrewAssignment, $this> */
    public function substituteFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substitute_for_assignment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CrewShift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(CrewShift::class);
    }

    /** @return HasMany<CrewTimesheet, $this> */
    public function timesheets(): HasMany
    {
        return $this->hasMany(CrewTimesheet::class);
    }

    /** @return HasMany<CrewExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(CrewExpense::class);
    }

    protected function moneyFormattingCurrency(): string
    {
        $code = $this->currency_code;

        return $code !== '' ? $code : $this->baseFormattingCurrency();
    }
}
