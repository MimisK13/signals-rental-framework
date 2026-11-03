<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Administration\Models\User;
use App\Domain\Crew\Database\Factories\CrewMemberSkillFactory;
use App\Domain\Crew\Enums\CrewProficiency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crew_member_id
 * @property int $crew_skill_id
 * @property CrewProficiency $proficiency
 * @property int|null $years_experience
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $verifier
 */
class CrewMemberSkill extends Model
{
    /** @use HasFactory<CrewMemberSkillFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'crew_member_id',
        'crew_skill_id',
        'proficiency',
        'years_experience',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'proficiency' => CrewProficiency::class,
            'years_experience' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CrewMember, $this> */
    public function crewMember(): BelongsTo
    {
        return $this->belongsTo(CrewMember::class);
    }

    /** @return BelongsTo<CrewSkill, $this> */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(CrewSkill::class, 'crew_skill_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
