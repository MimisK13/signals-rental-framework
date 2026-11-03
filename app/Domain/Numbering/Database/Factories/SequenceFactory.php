<?php

declare(strict_types=1);

namespace App\Domain\Numbering\Database\Factories;

use App\Domain\Numbering\Models\Sequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sequence>
 */
class SequenceFactory extends Factory
{
    protected $model = Sequence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(),
            'next_value' => 1,
        ];
    }
}
