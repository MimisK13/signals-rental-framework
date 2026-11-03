<?php

declare(strict_types=1);

namespace App\Domain\Projects\Models;

use App\Domain\Members\Models\Member;
use App\Domain\Projects\Database\Factories\ProjectFactory;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Quotations\Models\Quotation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int|null $member_id
 * @property int|null $venue_id
 * @property int|null $owned_by
 * @property ProjectStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 *
 * Central coordination aggregate for quotations and operational work.
 */
final class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

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

    /** @return HasMany<Quotation, $this> */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
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
