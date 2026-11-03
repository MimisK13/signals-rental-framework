<?php

declare(strict_types=1);

namespace App\Domain\Numbering\Models;

use App\Domain\CustomFields\Models\CustomField;
use App\Domain\Numbering\Database\Factories\AutoNumberSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $custom_field_id
 * @property string|null $prefix
 * @property string|null $suffix
 * @property int $next_value
 * @property-read CustomField $customField
 */
final class AutoNumberSequence extends Model
{
    /** @use HasFactory<AutoNumberSequenceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'custom_field_id',
        'prefix',
        'suffix',
        'next_value',
    ];

    protected static function newFactory(): AutoNumberSequenceFactory
    {
        return AutoNumberSequenceFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CustomField, $this>
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }
}
