<?php

declare(strict_types=1);

namespace App\Domain\Documents\Models;

use App\Domain\Administration\Models\User;
use App\Domain\Documents\Database\Factories\DocumentFactory;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Stores\Concerns\HasStoreScoping;
use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $store_id
 * @property int $document_template_id
 * @property int|null $generated_by_user_id
 * @property string $document_type
 * @property string $document_number
 * @property string $documentable_type
 * @property int $documentable_id
 * @property string|null $pdf_path
 * @property string|null $html_snapshot_path
 * @property string|null $public_token
 * @property DocumentStatus $status
 * @property array<string, mixed>|null $rendered_data
 * @property Carbon|null $public_expires_at
 * @property string|null $public_pin_hash
 * @property string $locale
 * @property string $currency_code
 * @property int $template_version
 * @property Carbon|null $emailed_at
 * @property string|null $emailed_to
 * @property Carbon|null $first_viewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasStoreScoping, SoftDeletes;

    protected static function newFactory(): DocumentFactory
    {
        return DocumentFactory::new();
    }

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
