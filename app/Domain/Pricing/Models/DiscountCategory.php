<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use App\Domain\Members\Models\Member;
use App\Domain\Pricing\Database\Factories\DiscountCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class DiscountCategory extends Model
{
    /** @use HasFactory<DiscountCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): DiscountCategoryFactory
    {
        return DiscountCategoryFactory::new();
    }

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
