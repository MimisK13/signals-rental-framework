<?php

declare(strict_types=1);

namespace App\Domain\Auth\Database\Factories;

use App\Domain\Administration\Models\User;
use App\Domain\Auth\Models\MagicLinkToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MagicLinkToken>
 */
final class MagicLinkTokenFactory extends Factory
{
    protected $model = MagicLinkToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', fake()->unique()->sha256()),
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => null,
        ];
    }

    /**
     * Indicate the token's expiry is in the past.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Indicate the token has already been consumed.
     */
    public function consumed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'consumed_at' => now(),
        ]);
    }
}
