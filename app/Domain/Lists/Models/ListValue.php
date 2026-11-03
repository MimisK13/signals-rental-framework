<?php

declare(strict_types=1);

namespace App\Domain\Lists\Models;

use App\Domain\Lists\Database\Factories\ListValueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $list_name_id
 * @property string $name
 * @property int|null $parent_id
 * @property int $sort_order
 * @property bool $is_system
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property string|null $plugin_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ListValue extends Model
{
    /** @use HasFactory<ListValueFactory> */
    use HasFactory;

    protected static function newFactory(): ListValueFactory
    {
        return ListValueFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'list_name_id',
        'name',
        'parent_id',
        'sort_order',
        'is_system',
        'is_active',
        'metadata',
        'plugin_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ListName, $this>
     */
    public function listName(): BelongsTo
    {
        return $this->belongsTo(ListName::class);
    }

    /**
     * @return BelongsTo<ListValue, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ListValue::class, 'parent_id');
    }

    /**
     * @return HasMany<ListValue, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ListValue::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Scope to active values.
     *
     * @param  Builder<ListValue>  $query
     * @return Builder<ListValue>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
