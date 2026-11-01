<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Crew\Database\Factories\CrewRoleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_supervisory
 * @property bool $requires_certification
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CrewRole extends Model
{
    /** @use HasFactory<CrewRoleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_supervisory',
        'requires_certification',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_supervisory' => 'boolean',
            'requires_certification' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<CrewRoleRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(CrewRoleRequirement::class);
    }

    /** @return HasMany<CrewMemberRole, $this> */
    public function memberRoles(): HasMany
    {
        return $this->hasMany(CrewMemberRole::class);
    }

    /** @return BelongsToMany<CrewMember, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(CrewMember::class, 'crew_member_roles')
            ->withPivot(['proficiency', 'is_primary', 'years_experience'])
            ->withTimestamps();
    }

    /** @return HasMany<CrewRequestLine, $this> */
    public function requestLines(): HasMany
    {
        return $this->hasMany(CrewRequestLine::class);
    }

    /** @return HasMany<CrewRateCard, $this> */
    public function rateCards(): HasMany
    {
        return $this->hasMany(CrewRateCard::class);
    }

    /**
     * Ordered, active roles for pickers.
     *
     * @param  Builder<CrewRole>  $query
     * @return Builder<CrewRole>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
