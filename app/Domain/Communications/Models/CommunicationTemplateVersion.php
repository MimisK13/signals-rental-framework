<?php

declare(strict_types=1);

namespace App\Domain\Communications\Models;

use App\Domain\Communications\Database\Factories\CommunicationTemplateVersionFactory;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $communication_template_id
 * @property int|null $edited_by_member_id
 * @property int $version
 * @property string|null $subject
 * @property string $body
 * @property string|null $change_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class CommunicationTemplateVersion extends Model
{
    /** @use HasFactory<CommunicationTemplateVersionFactory> */
    use HasFactory;

    protected static function newFactory(): CommunicationTemplateVersionFactory
    {
        return CommunicationTemplateVersionFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'communication_template_id',
        'edited_by_member_id',
        'version',
        'subject',
        'body',
        'change_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CommunicationTemplate, $this>
     */
    public function communicationTemplate(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'edited_by_member_id');
    }
}
