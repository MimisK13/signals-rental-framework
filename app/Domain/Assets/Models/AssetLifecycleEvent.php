<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use App\Domain\Administration\Models\User;
use App\Domain\Assets\Database\Factories\AssetLifecycleEventFactory;
use App\Domain\Assets\Enums\AssetLifecycleEventType;
use App\Domain\Assets\Enums\AssetLifecycleStatus;
use App\Domain\Assets\Enums\AssetRetirementDisposition;
use App\Domain\Attachments\Concerns\HasAttachments;
use App\Domain\Members\Models\Member;
use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property AssetLifecycleEventType $event_type
 * @property Carbon $occurred_at
 * @property int|null $proceeds_amount
 * @property string|null $proceeds_currency_code
 * @property int|null $buyer_member_id
 */
class AssetLifecycleEvent extends Model
{
    /** @use HasFactory<AssetLifecycleEventFactory> */
    use HasAttachments, HasFactory;

    protected static function newFactory(): AssetLifecycleEventFactory
    {
        return AssetLifecycleEventFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Asset lifecycle history is immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Asset lifecycle history cannot be deleted.');
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'asset_id',
        'event_type',
        'occurred_at',
        'actor_user_id',
        'actor_member_id',
        'previous_status',
        'new_status',
        'store_id',
        'location',
        'disposition',
        'proceeds_amount',
        'proceeds_currency_code',
        'buyer_member_id',
        'reference',
        'reason',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event_type' => AssetLifecycleEventType::class,
            'occurred_at' => 'datetime',
            'previous_status' => AssetLifecycleStatus::class,
            'new_status' => AssetLifecycleStatus::class,
            'disposition' => AssetRetirementDisposition::class,
            'proceeds_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function actorMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'actor_member_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'buyer_member_id');
    }

    public function hasTerminalDisposition(): bool
    {
        $disposition = $this->getAttribute('disposition');

        return $disposition instanceof AssetRetirementDisposition && in_array($disposition, [
            AssetRetirementDisposition::Sold,
            AssetRetirementDisposition::Disposed,
        ], true);
    }
}
