<?php

namespace App\Models;

use App\Models\Traits\HasStoreScoping;
use Database\Factories\DocumentSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSequence extends Model
{
    /** @use HasFactory<DocumentSequenceFactory> */
    use HasFactory, HasStoreScoping;

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
