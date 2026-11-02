<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Models;

use App\Domain\CustomFields\Database\Factories\CustomFieldFactory;
use App\Domain\CustomFields\Enums\CustomFieldType;
use App\Domain\Lists\Models\ListName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property string $module_type
 * @property CustomFieldType $field_type
 * @property int|null $custom_field_group_id
 * @property int|null $list_name_id
 * @property int $sort_order
 * @property bool $is_required
 * @property bool $is_searchable
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $validation_rules
 * @property array<int, array<string, mixed>>|null $visibility_rules
 * @property mixed $default_value
 * @property string|null $plugin_name
 * @property string|null $document_layout_name
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomField extends Model
{
    /** @use HasFactory<CustomFieldFactory> */
    use HasFactory;

    protected static function newFactory(): CustomFieldFactory
    {
        return CustomFieldFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'module_type',
        'field_type',
        'custom_field_group_id',
        'list_name_id',
        'sort_order',
        'is_required',
        'is_searchable',
        'settings',
        'validation_rules',
        'visibility_rules',
        'default_value',
        'plugin_name',
        'document_layout_name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field_type' => CustomFieldType::class,
            'is_required' => 'boolean',
            'is_searchable' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
            'validation_rules' => 'array',
            'visibility_rules' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CustomFieldGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomFieldGroup::class, 'custom_field_group_id');
    }

    /**
     * @return BelongsTo<ListName, $this>
     */
    public function listName(): BelongsTo
    {
        return $this->belongsTo(ListName::class);
    }

    /**
     * @return HasMany<CustomFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    /**
     * Scope to fields for a given module type.
     *
     * @param  Builder<CustomField>  $query
     * @return Builder<CustomField>
     */
    public function scopeForModule(Builder $query, string $moduleType): Builder
    {
        return $query->where('module_type', $moduleType);
    }

    /**
     * Scope to active fields.
     *
     * @param  Builder<CustomField>  $query
     * @return Builder<CustomField>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
