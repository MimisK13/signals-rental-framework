<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Attachments\Concerns\HasAttachments;
use App\Domain\Crew\Database\Factories\CrewRequestFactory;
use App\Domain\Crew\Enums\CrewRequestPriority;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\CustomFields\Concerns\HasCustomFields;
use App\Domain\Members\Models\Member;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Stores\Concerns\HasStoreScoping;
use App\Domain\Stores\Models\Store;
use App\Models\User;
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
 * @property int|null $opportunity_id
 * @property int|null $venue_member_id
 * @property string|null $reference
 * @property string $title
 * @property CrewRequestStatus $status
 * @property CrewRequestPriority $priority
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $timezone
 * @property string|null $location_name
 * @property string|null $notes
 * @property int|null $requested_by
 * @property Carbon|null $published_at
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Opportunity|null $opportunity
 * @property-read Member|null $venue
 * @property-read User|null $requestedBy
 * @property-read User|null $approvedBy
 */
class CrewRequest extends Model
{
    /** @use HasFactory<CrewRequestFactory> */
    use HasAttachments, HasCustomFields, HasFactory, HasStoreScoping, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'store_id',
        'project_id',
        'opportunity_id',
        'venue_member_id',
        'reference',
        'title',
        'status',
        'priority',
        'starts_at',
        'ends_at',
        'timezone',
        'location_name',
        'notes',
        'requested_by',
        'published_at',
        'approved_at',
        'approved_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => CrewRequestStatus::class,
            'priority' => CrewRequestPriority::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    /** @return BelongsTo<Opportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'venue_member_id');
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

    /** @return HasMany<CrewRequestLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CrewRequestLine::class)->orderBy('sort_order');
    }

    /** @return HasMany<CrewAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(CrewAssignment::class);
    }
}
