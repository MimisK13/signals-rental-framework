<?php

namespace App\Models;

use App\Enums\StockMethod;
use Database\Factories\InspectionPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class InspectionPolicy extends Model
{
    /** @use HasFactory<InspectionPolicyFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id', 'name', 'description', 'interval_months', 'is_active', 'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'interval_months' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InspectionPolicy $policy): void {
            if ((int) $policy->interval_months < 1) {
                throw ValidationException::withMessages(['interval_months' => 'The inspection interval must be greater than zero.']);
            }

            $product = $policy->product ?: Product::query()->find($policy->product_id);
            if ($product?->stock_method !== StockMethod::Serialised) {
                throw ValidationException::withMessages(['product_id' => 'Inspection policies apply only to serialized Products.']);
            }
        });
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<AssetInspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(AssetInspection::class);
    }
}
