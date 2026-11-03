<?php

declare(strict_types=1);

namespace App\Domain\Products\Models;

use App\Domain\Products\Database\Factories\AccessoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $accessory_product_id
 * @property numeric-string $quantity
 * @property bool $included
 * @property bool $zero_priced
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Product|null $accessoryProduct
 */
class Accessory extends Model
{
    /** @use HasFactory<AccessoryFactory> */
    use HasFactory;

    protected static function newFactory(): AccessoryFactory
    {
        return AccessoryFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'accessory_product_id',
        'quantity',
        'included',
        'zero_priced',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'included' => 'boolean',
            'zero_priced' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function accessoryProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'accessory_product_id');
    }
}
