<?php

namespace App\Models;

use Database\Factories\DiscountCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountCategory extends Model
{
    /** @use HasFactory<DiscountCategoryFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * @return HasMany<DiscountRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(DiscountRule::class)->orderBy('sort_order')->orderBy('id');
    }
}
