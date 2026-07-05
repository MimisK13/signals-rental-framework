<?php

namespace App\Models;

use Database\Factories\DocumentTemplateVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $page_config
 */
class DocumentTemplateVersion extends Model
{
    /** @use HasFactory<DocumentTemplateVersionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'document_template_id',
        'edited_by_member_id',
        'version',
        'body_html',
        'header_html',
        'footer_html',
        'styles_css',
        'page_config',
        'change_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_config' => 'array',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DocumentTemplate, $this>
     */
    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'edited_by_member_id');
    }
}
