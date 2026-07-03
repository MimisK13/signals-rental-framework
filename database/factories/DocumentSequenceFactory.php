<?php

namespace Database\Factories;

use App\Models\DocumentSequence;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentSequence>
 */
class DocumentSequenceFactory extends Factory
{
    protected $model = DocumentSequence::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'document_type' => fake()->randomElement(['quote', 'invoice', 'delivery_note']),
            'prefix' => '',
            'suffix' => '',
            'include_year' => true,
            'include_month' => false,
            'next_number' => 1,
            'padding' => 4,
            'reset_frequency' => 'never',
            'current_period_year' => null,
            'current_period_month' => null,
        ];
    }

    public function withPrefix(string $prefix): static
    {
        return $this->state(fn (): array => ['prefix' => $prefix]);
    }
}
