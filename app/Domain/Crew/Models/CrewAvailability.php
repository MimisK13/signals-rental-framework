<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Administration\Models\User;
use App\Domain\Attachments\Concerns\HasAttachments;
use App\Domain\Crew\Database\Factories\CrewAvailabilityFactory;
use App\Domain\Crew\Enums\CrewAvailabilityStatus;
use App\Domain\Crew\Enums\CrewAvailabilityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crew_member_id
 * @property CrewAvailabilityType $type
 * @property CrewAvailabilityStatus $status
 * @property bool $all_day
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $timezone
 * @property string|null $reason
 * @property string|null $notes
 * @property int|null $requested_by
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $requestedBy
 * @property-read User|null $approvedBy
 */
class CrewAvailability extends Model
{
    /** @use HasFactory<CrewAvailabilityFactory> */
    use HasAttachments, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'crew_member_id',
        'type',
        'status',
        'all_day',
        'starts_at',
        'ends_at',
        'timezone',
        'reason',
        'notes',
        'requested_by',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CrewAvailabilityType::class,
            'status' => CrewAvailabilityStatus::class,
            'all_day' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CrewMember, $this> */
    public function crewMember(): BelongsTo
    {
        return $this->belongsTo(CrewMember::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
