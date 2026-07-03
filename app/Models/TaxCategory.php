<?php

namespace App\Models;

use App\Enums\TaxCategoryType;
use Database\Factories\TaxCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxCategory extends Model
{
    /** @use HasFactory<TaxCategoryFactory> */
    use HasFactory;

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
