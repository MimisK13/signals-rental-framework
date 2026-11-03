<?php

declare(strict_types=1);

namespace App\Domain\Members\Models;

use App\Domain\Members\Database\Factories\MemberRelationshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $member_id
 * @property int $related_member_id
 * @property string|null $relationship_type
 * @property bool $is_primary
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MemberRelationship extends Model
{
    /** @use HasFactory<MemberRelationshipFactory> */
    use HasFactory;

    protected static function newFactory(): MemberRelationshipFactory
    {
        return MemberRelationshipFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'member_id',
        'related_member_id',
        'relationship_type',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * The contact member in this relationship.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The organisation member in this relationship.
     *
     * @return BelongsTo<Member, $this>
     */
    public function relatedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'related_member_id');
    }
}
