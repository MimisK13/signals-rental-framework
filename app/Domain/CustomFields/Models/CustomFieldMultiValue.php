<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Models;

use App\Domain\CustomFields\Database\Factories\CustomFieldMultiValueFactory;
use App\Domain\Lists\Models\ListValue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $custom_field_value_id
 * @property int $list_value_id
 */
class CustomFieldMultiValue extends Model
{
    /** @use HasFactory<CustomFieldMultiValueFactory> */
    use HasFactory;

    protected static function newFactory(): CustomFieldMultiValueFactory
    {
        return CustomFieldMultiValueFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'custom_field_value_id',
        'list_value_id',
    ];

    /**
     * @return BelongsTo<CustomFieldValue, $this>
     */
    public function customFieldValue(): BelongsTo
    {
        return $this->belongsTo(CustomFieldValue::class);
    }

    /**
     * @return BelongsTo<ListValue, $this>
     */
    public function listValue(): BelongsTo
    {
        return $this->belongsTo(ListValue::class);
    }
}
