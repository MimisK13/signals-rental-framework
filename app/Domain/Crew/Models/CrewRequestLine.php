<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Crew\Database\Factories\CrewRequestLineFactory;
use App\Domain\Crew\Enums\CrewProficiency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crew_request_id
 * @property int $crew_role_id
 * @property int $quantity
 * @property int $filled_quantity
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property CrewProficiency|null $min_proficiency
 * @property string|null $notes
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CrewRequestLine extends Model
{
    /** @use HasFactory<CrewRequestLineFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'crew_request_id',
        'crew_role_id',
        'quantity',
        'filled_quantity',
        'starts_at',
        'ends_at',
        'min_proficiency',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'filled_quantity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'min_proficiency' => CrewProficiency::class,
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<CrewRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(CrewRequest::class, 'crew_request_id');
    }

    /** @return BelongsTo<CrewRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(CrewRole::class, 'crew_role_id');
    }

    /** @return HasMany<CrewRequestLineRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(CrewRequestLineRequirement::class);
    }

    /** @return HasMany<CrewAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(CrewAssignment::class);
    }

    public function effectiveStartsAt(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->starts_at ?? $this->request->starts_at);
    }

    public function effectiveEndsAt(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->ends_at ?? $this->request->ends_at);
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->filled_quantity);
    }
}
