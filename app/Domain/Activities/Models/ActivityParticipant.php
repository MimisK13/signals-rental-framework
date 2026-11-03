<?php

declare(strict_types=1);

namespace App\Domain\Activities\Models;

use App\Domain\Activities\Database\Factories\ActivityParticipantFactory;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $activity_id
 * @property int $member_id
 * @property bool $mute
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Activity|null $activity
 * @property-read Member|null $member
 */
final class ActivityParticipant extends Model
{
    /** @use HasFactory<ActivityParticipantFactory> */
    use HasFactory;

    protected static function newFactory(): ActivityParticipantFactory
    {
        return ActivityParticipantFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'activity_id',
        'member_id',
        'mute',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mute' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
