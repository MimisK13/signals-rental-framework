<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Crew\Database\Factories\CrewMemberRoleFactory;
use App\Domain\Crew\Enums\CrewProficiency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crew_member_id
 * @property int $crew_role_id
 * @property CrewProficiency $proficiency
 * @property bool $is_primary
 * @property int|null $years_experience
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CrewMemberRole extends Model
{
    /** @use HasFactory<CrewMemberRoleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'crew_member_id',
        'crew_role_id',
        'proficiency',
        'is_primary',
        'years_experience',
    ];

    protected function casts(): array
    {
        return [
            'proficiency' => CrewProficiency::class,
            'is_primary' => 'boolean',
            'years_experience' => 'integer',
        ];
    }

    /** @return BelongsTo<CrewMember, $this> */
    public function crewMember(): BelongsTo
    {
        return $this->belongsTo(CrewMember::class);
    }

    /** @return BelongsTo<CrewRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(CrewRole::class, 'crew_role_id');
    }
}
