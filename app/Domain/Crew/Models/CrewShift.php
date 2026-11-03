<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Administration\Models\User;
use App\Domain\Crew\Database\Factories\CrewShiftFactory;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Enums\CrewShiftType;
use App\Domain\Members\Models\Member;
use App\Domain\Projects\Models\Project;
use App\Domain\Stores\Concerns\HasStoreScoping;
use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $store_id
 * @property int $project_id
 * @property int|null $crew_assignment_id
 * @property int|null $crew_member_id
 * @property int|null $venue_member_id
 * @property CrewShiftStatus $status
 * @property CrewShiftType $type
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $timezone
 * @property int $break_minutes
 * @property bool $is_overnight
 * @property string|null $location_name
 * @property string|null $notes
 * @property int $sequence
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CrewAssignment|null $assignment
 * @property-read CrewMember|null $crewMember
 * @property-read Member|null $venue
 * @property-read User|null $creator
 */
class CrewShift extends Model
{
    /** @use HasFactory<CrewShiftFactory> */
    use HasFactory, HasStoreScoping, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'store_id',
        'project_id',
        'crew_assignment_id',
        'crew_member_id',
        'venue_member_id',
        'status',
        'type',
        'starts_at',
        'ends_at',
        'timezone',
        'break_minutes',
        'is_overnight',
        'location_name',
        'notes',
        'sequence',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CrewShiftStatus::class,
            'type' => CrewShiftType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'break_minutes' => 'integer',
            'is_overnight' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    /** @return BelongsTo<Member, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'venue_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CrewShiftBreak, $this> */
    public function breaks(): HasMany
    {
        return $this->hasMany(CrewShiftBreak::class)->orderBy('starts_at');
    }

    /** @return HasMany<CrewTimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(CrewTimeEntry::class);
    }
}
