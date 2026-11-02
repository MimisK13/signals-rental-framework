<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Models;

use App\Domain\CustomFields\Database\Factories\CustomFieldValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $custom_field_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string|null $value_string
 * @property string|null $value_text
 * @property int|null $value_integer
 * @property string|null $value_decimal
 * @property bool|null $value_boolean
 * @property Carbon|null $value_date
 * @property Carbon|null $value_datetime
 * @property string|null $value_time
 * @property array<mixed>|null $value_json
 */
class CustomFieldValue extends Model
{
    /** @use HasFactory<CustomFieldValueFactory> */
    use HasFactory;

    protected static function newFactory(): CustomFieldValueFactory
    {
        return CustomFieldValueFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'custom_field_id',
        'entity_type',
        'entity_id',
        'value_string',
        'value_text',
        'value_integer',
        'value_decimal',
        'value_boolean',
        'value_date',
        'value_datetime',
        'value_time',
        'value_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_boolean' => 'boolean',
            'value_decimal' => 'decimal:4',
            'value_date' => 'date',
            'value_datetime' => 'datetime',
            'value_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CustomField, $this>
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
