<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Crew\Database\Factories\CrewMemberQualificationFactory;
use App\Domain\Crew\Enums\CrewQualificationStatus;
use App\Models\Traits\HasAttachments;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crew_member_id
 * @property int $crew_qualification_id
 * @property string|null $reference_number
 * @property Carbon|null $issued_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $verifier
 */
class CrewMemberQualification extends Model
{
    /** @use HasFactory<CrewMemberQualificationFactory> */
    use HasAttachments, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'crew_member_id',
        'crew_qualification_id',
        'reference_number',
        'issued_at',
        'expires_at',
        'verified_at',
        'verified_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CrewMember, $this> */
    public function crewMember(): BelongsTo
    {
        return $this->belongsTo(CrewMember::class);
    }

    /** @return BelongsTo<CrewQualification, $this> */
    public function qualification(): BelongsTo
    {
        return $this->belongsTo(CrewQualification::class, 'crew_qualification_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Derived qualification status. Never persisted.
     */
    public function status(): CrewQualificationStatus
    {
        if ($this->expires_at !== null && $this->expires_at->lt(today())) {
            return CrewQualificationStatus::Expired;
        }

        if ($this->expires_at !== null) {
            $warningDays = (int) settings('crew.qualification_expiry_warning_days', 30);

            if ($this->expires_at->lte(today()->addDays($warningDays))) {
                return CrewQualificationStatus::ExpiringSoon;
            }
        }

        if ($this->verified_at === null) {
            return CrewQualificationStatus::PendingVerification;
        }

        return CrewQualificationStatus::Valid;
    }
}
