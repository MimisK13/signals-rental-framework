<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Database\Factories;

use App\Domain\Webhooks\Models\Webhook;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'url' => fake()->url(),
            'secret' => Str::random(32),
            'events' => ['user.created', 'user.updated'],
            'is_active' => true,
            'consecutive_failures' => 0,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'disabled_at' => now(),
        ]);
    }
}
