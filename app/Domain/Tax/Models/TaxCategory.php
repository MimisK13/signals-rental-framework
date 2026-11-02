<?php

declare(strict_types=1);

namespace App\Domain\Tax\Models;

use App\Domain\Tax\Database\Factories\TaxCategoryFactory;
use App\Domain\Tax\Enums\TaxCategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property TaxCategoryType $type
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class TaxCategory extends Model
{
    /** @use HasFactory<TaxCategoryFactory> */
    use HasFactory;

    protected static function newFactory(): TaxCategoryFactory
    {
        return TaxCategoryFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'is_default',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaxCategoryType::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TaxRule, $this>
     */
    public function taxRules(): HasMany
    {
        return $this->hasMany(TaxRule::class);
    }
}
