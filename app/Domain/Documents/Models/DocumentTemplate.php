<?php

declare(strict_types=1);

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Database\Factories\DocumentTemplateFactory;
use App\Domain\Documents\Services\DocumentTypeRegistry;
use App\Domain\Invoices\Models\CreditNote;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Models\Member;
use App\Models\Store;
use App\Models\Traits\HasStoreScoping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $store_id
 * @property int|null $created_by_member_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $document_type
 * @property class-string $model_type
 * @property string $body_html
 * @property string|null $header_html
 * @property string|null $footer_html
 * @property string|null $styles_css
 * @property array<string, mixed>|null $page_config
 * @property array<string, mixed>|null $branding_config
 * @property bool $is_default
 * @property bool $is_system
 * @property bool $is_active
 * @property int $version
 * @property Carbon|null $last_edited_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DocumentTemplate extends Model
{
    /** @use HasFactory<DocumentTemplateFactory> */
    use HasFactory, HasStoreScoping, SoftDeletes;

    protected static function newFactory(): DocumentTemplateFactory
    {
        return DocumentTemplateFactory::new();
    }

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
        $registry = app(DocumentTypeRegistry::class);

        if ($registry->has($this->document_type)) {
            return $registry->resolverType($this->document_type);
        }

        return match ($this->model_type) {
            Opportunity::class => 'opportunity',
            Member::class => 'statement',
            Invoice::class => 'invoice',
            CreditNote::class => 'credit_note',
            default => throw new \InvalidArgumentException("Unsupported document template model type [{$this->model_type}]."),
        };
    }

    /**
     * @param  Builder<DocumentTemplate>  $query
     * @return Builder<DocumentTemplate>
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }
}
