<?php

declare(strict_types=1);

namespace App\Domain\Assets\Database\Factories;

use App\Domain\Assets\Enums\AssetOwnershipType;
use App\Domain\Assets\Models\Asset;
use App\Domain\Products\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->serialised(),
            'lifecycle_status' => 'active',
            'ownership_type' => AssetOwnershipType::Unknown,
            'owner_member_id' => null,
            'acquired_at' => null,
            'acquisition_amount' => null,
            'acquisition_currency_code' => null,
            'acquisition_exchange_rate' => null,
            'base_currency_code' => null,
            'base_acquisition_amount' => null,
            'supplier_member_id' => null,
            'purchase_order_item_id' => null,
            'estimated_resale_value' => null,
            'estimated_resale_currency_code' => null,
        ];
    }
}
