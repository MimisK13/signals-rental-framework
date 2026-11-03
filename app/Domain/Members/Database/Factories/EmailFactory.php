<?php

declare(strict_types=1);

namespace App\Domain\Members\Database\Factories;

use App\Domain\Members\Models\Email;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Email>
 */
class EmailFactory extends Factory
{
    protected $model = Email::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'emailable_type' => Member::class,
            'emailable_id' => Member::factory(),
            'address' => fake()->unique()->safeEmail(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
