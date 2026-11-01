<?php

declare(strict_types=1);

namespace App\Domain\Currency\Database\Factories;

use App\Domain\Currency\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
final class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->currencyCode(),
            'name' => fake()->word().' Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'symbol_position' => 'before',
            'thousand_separator' => ',',
            'decimal_separator' => '.',
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
