<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property ProjectStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 *
 * Planning/commercial grouping for related Opportunities.
 *
 * A Project has no Store scope. Each Opportunity remains authoritative for its
 * own Store, operational dates, availability, and financial projection.
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'member_id',
        'venue_id',
        'owned_by',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'venue_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'owned_by');
    }

    /** @return HasMany<Opportunity, $this> */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * Scope to archived (soft-deleted) projects only.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->onlyTrashed();
    }

    /**
     * Scope to include archived (soft-deleted) projects.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeWithArchived(Builder $query): Builder
    {
        return $query->withTrashed();
    }
}
