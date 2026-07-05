<?php

namespace App\Models;

use Database\Factories\CommunicationTemplateVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTemplateVersion extends Model
{
    /** @use HasFactory<CommunicationTemplateVersionFactory> */
    use HasFactory;

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
