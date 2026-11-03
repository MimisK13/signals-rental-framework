<?php

declare(strict_types=1);

namespace App\Domain\Members\Models;

use App\Domain\Members\Database\Factories\MembershipFactory;
use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $member_id
 * @property int|null $store_id
 * @property bool $is_owner
 * @property bool $is_admin
 * @property bool $is_active
 * @property string|null $job_title
 * @property string|null $department
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected static function newFactory(): MembershipFactory
    {
        return MembershipFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'member_id',
        'store_id',
        'is_owner',
        'is_admin',
        'is_active',
        'job_title',
        'department',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
