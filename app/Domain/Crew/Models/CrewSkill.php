<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Crew\Database\Factories\CrewSkillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $category
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CrewSkill extends Model
{
    /** @use HasFactory<CrewSkillFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<CrewMemberSkill, $this> */
    public function memberSkills(): HasMany
    {
        return $this->hasMany(CrewMemberSkill::class);
    }

    /** @return BelongsToMany<CrewMember, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(CrewMember::class, 'crew_member_skills')
            ->withPivot(['proficiency', 'years_experience', 'verified_at', 'verified_by'])
            ->withTimestamps();
    }

    /** @return HasMany<CrewRoleRequirement, $this> */
    public function roleRequirements(): HasMany
    {
        return $this->hasMany(CrewRoleRequirement::class);
    }

    /** @return HasMany<CrewRequestLineRequirement, $this> */
    public function requestLineRequirements(): HasMany
    {
        return $this->hasMany(CrewRequestLineRequirement::class);
    }
}
