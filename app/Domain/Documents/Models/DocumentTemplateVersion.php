<?php

declare(strict_types=1);

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Database\Factories\DocumentTemplateVersionFactory;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_template_id
 * @property int|null $edited_by_member_id
 * @property int $version
 * @property string $body_html
 * @property string|null $header_html
 * @property string|null $footer_html
 * @property string|null $styles_css
 * @property array<string, mixed>|null $page_config
 * @property string|null $change_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DocumentTemplateVersion extends Model
{
    /** @use HasFactory<DocumentTemplateVersionFactory> */
    use HasFactory;

    protected static function newFactory(): DocumentTemplateVersionFactory
    {
        return DocumentTemplateVersionFactory::new();
    }

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
