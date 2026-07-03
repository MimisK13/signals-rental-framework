<?php

namespace App\Models;

use App\Models\Traits\HasStoreScoping;
use Database\Factories\DocumentTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<string, mixed>|null $page_config
 * @property array<string, mixed>|null $branding_config
 */
class DocumentTemplate extends Model
{
    /** @use HasFactory<DocumentTemplateFactory> */
    use HasFactory, HasStoreScoping, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'store_id',
        'created_by_member_id',
        'name',
        'slug',
        'description',
        'document_type',
        'model_type',
        'body_html',
        'header_html',
        'footer_html',
        'styles_css',
        'page_config',
        'branding_config',
        'is_default',
        'is_system',
        'is_active',
        'version',
        'last_edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_config' => 'array',
            'branding_config' => 'array',
            'is_default' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
            'last_edited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by_member_id');
    }

    /**
     * @return HasMany<DocumentTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentTemplateVersion::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Registry key for the template's data resolver (distinct from document_type).
     */
    public function resolverType(): string
    {
        return match ($this->model_type) {
            Opportunity::class => 'opportunity',
            default => throw new \InvalidArgumentException("Unsupported document template model type [{$this->model_type}]."),
        };
    }
}
