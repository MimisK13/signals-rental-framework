<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Attachments\Concerns\HasAttachments;
use App\Domain\Crew\Database\Factories\CrewMemberFactory;
use App\Domain\Crew\Enums\CrewEngagementType;
use App\Domain\Crew\Enums\CrewMemberStatus;
use App\Domain\CustomFields\Concerns\HasCustomFields;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A schedulable person. Never requires a linked User; `member_id` and `user_id`
 * are optional links to the CRM identity and an optional login.
 *
 * @property int $id
 * @property int|null $member_id
 * @property int|null $user_id
 * @property int|null $supplier_member_id
 * @property CrewEngagementType $engagement_type
 * @property CrewMemberStatus $status
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $job_title
 * @property string $home_timezone
 * @property string|null $home_postcode
 * @property int|null $travel_radius_km
 * @property string|null $internal_notes
 * @property string|null $external_reference
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $last_assigned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Member|null $member
 * @property-read User|null $user
 * @property-read Member|null $supplier
 */
class CrewMember extends Model
{
    /** @use HasFactory<CrewMemberFactory> */
    use HasAttachments, HasCustomFields, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'member_id',
        'user_id',
        'supplier_member_id',
        'engagement_type',
        'status',
        'name',
        'email',
        'phone',
        'job_title',
        'home_timezone',
        'home_postcode',
        'travel_radius_km',
        'internal_notes',
        'external_reference',
        'starts_on',
        'ends_on',
        'last_assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'engagement_type' => CrewEngagementType::class,
            'status' => CrewMemberStatus::class,
            'travel_radius_km' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'last_assigned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'supplier_member_id');
    }

    /** @return HasMany<CrewMemberRole, $this> */
    public function memberRoles(): HasMany
    {
        return $this->hasMany(CrewMemberRole::class);
    }

    /** @return BelongsToMany<CrewRole, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(CrewRole::class, 'crew_member_roles')
            ->withPivot(['proficiency', 'is_primary', 'years_experience'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<CrewSkill, $this> */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(CrewSkill::class, 'crew_member_skills')
            ->withPivot(['proficiency', 'years_experience', 'verified_at', 'verified_by'])
            ->withTimestamps();
    }

    /** @return HasMany<CrewMemberQualification, $this> */
    public function qualifications(): HasMany
    {
        return $this->hasMany(CrewMemberQualification::class);
    }

    /** @return HasMany<CrewRateCard, $this> */
    public function rateCards(): HasMany
    {
        return $this->hasMany(CrewRateCard::class);
    }

    /** @return HasMany<CrewAvailability, $this> */
    public function availabilities(): HasMany
    {
        return $this->hasMany(CrewAvailability::class);
    }

    /** @return HasMany<CrewAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(CrewAssignment::class);
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

    public function isActive(): bool
    {
        return $this->status === CrewMemberStatus::Active;
    }
}
