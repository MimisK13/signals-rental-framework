<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Crew\Database\Factories\CrewRequestLineRequirementFactory;
use App\Domain\Crew\Enums\CrewProficiency;
use App\Domain\Crew\Enums\CrewRequirementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crew_request_line_id
 * @property CrewRequirementType $requirement_type
 * @property int|null $crew_skill_id
 * @property int|null $crew_qualification_id
 * @property bool $is_mandatory
 * @property CrewProficiency|null $min_proficiency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CrewSkill|null $skill
 * @property-read CrewQualification|null $qualification
 */
class CrewRequestLineRequirement extends Model
{
    /** @use HasFactory<CrewRequestLineRequirementFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'crew_request_line_id',
        'requirement_type',
        'crew_skill_id',
        'crew_qualification_id',
        'is_mandatory',
        'min_proficiency',
    ];

    protected function casts(): array
    {
        return [
            'requirement_type' => CrewRequirementType::class,
            'is_mandatory' => 'boolean',
            'min_proficiency' => CrewProficiency::class,
        ];
    }

    /** @return BelongsTo<CrewRequestLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(CrewRequestLine::class, 'crew_request_line_id');
    }

    /** @return BelongsTo<CrewSkill, $this> */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(CrewSkill::class, 'crew_skill_id');
    }

    /** @return BelongsTo<CrewQualification, $this> */
    public function qualification(): BelongsTo
    {
        return $this->belongsTo(CrewQualification::class, 'crew_qualification_id');
    }
}
