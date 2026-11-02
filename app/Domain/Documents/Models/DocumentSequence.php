<?php

declare(strict_types=1);

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Database\Factories\DocumentSequenceFactory;
use App\Models\Store;
use App\Models\Traits\HasStoreScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $store_id
 * @property string $document_type
 * @property bool $is_entity_number
 * @property string $prefix
 * @property string $suffix
 * @property bool $include_year
 * @property bool $include_month
 * @property int $next_number
 * @property int $padding
 * @property string $reset_frequency
 * @property int|null $current_period_year
 * @property int|null $current_period_month
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DocumentSequence extends Model
{
    /** @use HasFactory<DocumentSequenceFactory> */
    use HasFactory, HasStoreScoping;

    protected static function newFactory(): DocumentSequenceFactory
    {
        return DocumentSequenceFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'store_id',
        'document_type',
        'is_entity_number',
        'prefix',
        'suffix',
        'include_year',
        'include_month',
        'next_number',
        'padding',
        'reset_frequency',
        'current_period_year',
        'current_period_month',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'include_year' => 'boolean',
            'include_month' => 'boolean',
            'is_entity_number' => 'boolean',
            'next_number' => 'integer',
            'padding' => 'integer',
            'current_period_year' => 'integer',
            'current_period_month' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
