<?php

namespace App\Models;

use Database\Factories\BundleRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BundleRule extends Model
{
    /** @use HasFactory<BundleRuleFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'bundle_percent',
        'bundle_fixed_price_minor',
        'criteria',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bundle_percent' => 'decimal:2',
            'bundle_fixed_price_minor' => 'integer',
            'criteria' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isPercent(): bool
    {
        return $this->bundle_percent !== null;
    }

    public function isFixed(): bool
    {
        return $this->bundle_fixed_price_minor !== null;
    }
}
