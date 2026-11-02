<?php

declare(strict_types=1);

namespace App\Domain\Assets\Database\Factories;

use App\Domain\Assets\Enums\AssetExpenseType;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetExpense> */
class AssetExpenseFactory extends Factory
{
    protected $model = AssetExpense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'expense_type' => AssetExpenseType::Other,
            'amount' => 10000,
            'currency_code' => 'GBP',
            'exchange_rate' => '1',
            'base_currency_code' => 'GBP',
            'base_amount' => 10000,
            'occurred_at' => now(),
            'supplier_member_id' => null,
            'description' => null,
            'reference' => null,
        ];
    }
}
