<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Crew\Database\Factories\CrewQualificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $issuer
 * @property string|null $description
 * @property int|null $validity_months
 * @property bool $requires_expiry
 * @property bool $is_mandatory
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CrewQualification extends Model
{
    /** @use HasFactory<CrewQualificationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'issuer',
        'description',
        'validity_months',
        'requires_expiry',
        'is_mandatory',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'validity_months' => 'integer',
            'requires_expiry' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<CrewMemberQualification, $this> */
    public function memberQualifications(): HasMany
    {
        return $this->hasMany(CrewMemberQualification::class);
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
