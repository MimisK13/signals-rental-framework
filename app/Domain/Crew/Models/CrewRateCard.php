<?php

declare(strict_types=1);

namespace App\Domain\Crew\Models;

use App\Domain\Crew\Database\Factories\CrewRateCardFactory;
use App\Domain\Crew\Enums\CrewEngagementType;
use App\Domain\Stores\Models\Store;
use App\Models\Traits\FormatsMoney;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Effective-dated compensation policy. A null store_id makes the card
 * company-wide; store-specific cards override it during rate resolution.
 *
 * @property int $id
 * @property int|null $store_id
 * @property int|null $crew_member_id
 * @property int|null $crew_role_id
 * @property CrewEngagementType|null $engagement_type
 * @property string $currency_code
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $hourly_rate_minor
 * @property int|null $daily_rate_minor
 * @property int|null $fixed_rate_minor
 * @property string $overtime_multiplier
 * @property string|null $weekend_multiplier
 * @property string|null $night_multiplier
 * @property string|null $holiday_multiplier
 * @property string|null $min_call_hours
 * @property string|null $daily_call_hours
 * @property int|null $travel_rate_minor
 * @property int|null $per_diem_minor
 * @property string|null $notes
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Store|null $store
 * @property-read CrewMember|null $crewMember
 * @property-read CrewRole|null $role
 * @property-read User|null $creator
 */
class CrewRateCard extends Model
{
    /** @use HasFactory<CrewRateCardFactory> */
    use FormatsMoney, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'store_id',
        'crew_member_id',
        'crew_role_id',
        'engagement_type',
        'currency_code',
        'effective_from',
        'effective_to',
        'hourly_rate_minor',
        'daily_rate_minor',
        'fixed_rate_minor',
        'overtime_multiplier',
        'weekend_multiplier',
        'night_multiplier',
        'holiday_multiplier',
        'min_call_hours',
        'daily_call_hours',
        'travel_rate_minor',
        'per_diem_minor',
        'notes',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'engagement_type' => CrewEngagementType::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
            'hourly_rate_minor' => 'integer',
            'daily_rate_minor' => 'integer',
            'fixed_rate_minor' => 'integer',
            'overtime_multiplier' => 'decimal:2',
            'weekend_multiplier' => 'decimal:2',
            'night_multiplier' => 'decimal:2',
            'holiday_multiplier' => 'decimal:2',
            'min_call_hours' => 'decimal:2',
            'daily_call_hours' => 'decimal:2',
            'travel_rate_minor' => 'integer',
            'per_diem_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function moneyFormattingCurrency(): string
    {
        $code = $this->currency_code;

        return $code !== '' ? $code : $this->baseFormattingCurrency();
    }
}
