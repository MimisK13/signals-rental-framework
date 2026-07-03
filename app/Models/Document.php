<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Traits\HasStoreScoping;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property DocumentStatus $status
 * @property array<string, mixed>|null $rendered_data
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasStoreScoping, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'store_id',
        'document_template_id',
        'generated_by_user_id',
        'document_type',
        'document_number',
        'documentable_type',
        'documentable_id',
        'pdf_path',
        'html_snapshot_path',
        'public_token',
        'public_expires_at',
        'public_pin_hash',
        'status',
        'rendered_data',
        'locale',
        'currency_code',
        'template_version',
        'emailed_at',
        'emailed_to',
        'first_viewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'rendered_data' => 'array',
            'template_version' => 'integer',
            'public_expires_at' => 'datetime',
            'emailed_at' => 'datetime',
            'first_viewed_at' => 'datetime',
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
     * @return BelongsTo<DocumentTemplate, $this>
     */
    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
