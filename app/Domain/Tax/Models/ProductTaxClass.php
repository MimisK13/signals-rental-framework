<?php

declare(strict_types=1);

namespace App\Domain\Tax\Models;

use App\Domain\Tax\Database\Factories\ProductTaxClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ProductTaxClass extends Model
{
    /** @use HasFactory<ProductTaxClassFactory> */
    use HasFactory;

    protected static function newFactory(): ProductTaxClassFactory
    {
        return ProductTaxClassFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
