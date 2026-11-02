<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Models;

use App\Domain\CustomFields\Database\Factories\CustomFieldGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property string|null $plugin_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomFieldGroup extends Model
{
    /** @use HasFactory<CustomFieldGroupFactory> */
    use HasFactory;

    protected static function newFactory(): CustomFieldGroupFactory
    {
        return CustomFieldGroupFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'plugin_name',
    ];

    /**
     * @return HasMany<CustomField, $this>
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class)->orderBy('sort_order');
    }
}
