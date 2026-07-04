<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use App\Models\VirtualStockIntake;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<VirtualStockIntake>
 */
class VirtualStockIntakeFactory extends Factory
{
    protected $model = VirtualStockIntake::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = Carbon::parse(fake()->dateTimeBetween('+1 week', '+2 weeks'))->startOfDay();
        $until = $from->copy()->addDays(5);

        return [
            'id' => app(SequenceAllocator::class)->next('virtual_stock_intakes'),
            'state_id' => snowflake_id(),
            'number' => 'VS-'.fake()->unique()->numerify('####'),
            'status' => 'draft',
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'available_from' => $from,
            'available_until' => $until,
            'unit_cost' => fake()->numberBetween(1000, 5000),
            'currency_code' => 'GBP',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (): array => [
            'status' => 'received',
            'confirmed_at' => now()->subDay(),
            'received_at' => now(),
        ]);
    }
}
