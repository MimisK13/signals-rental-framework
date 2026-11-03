<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Database\Factories;

use App\Domain\Webhooks\Models\InboundWebhookSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InboundWebhookSource>
 */
class InboundWebhookSourceFactory extends Factory
{
    protected $model = InboundWebhookSource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' Webhook';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'secret' => Str::random(32),
            'signature_algo' => 'sha256',
            'signature_header' => 'X-Signature',
            'expected_events' => ['order.created', 'order.updated'],
            'price_weight' => 1,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
