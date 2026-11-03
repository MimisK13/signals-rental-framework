<?php

declare(strict_types=1);

namespace App\Domain\API\Database\Factories;

use App\Domain\API\Enums\MeteringChannel;
use App\Domain\API\Models\UsageDaily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageDaily>
 */
final class UsageDailyFactory extends Factory
{
    protected $model = UsageDaily::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->date(),
            'channel' => MeteringChannel::Api->value,
            'personal_access_token_id' => null,
            'inbound_webhook_source_id' => null,
            'key' => 'api.v1.'.fake()->word().'.index',
            'resource' => fake()->word(),
            'status_class' => fake()->randomElement(['2xx', '4xx', '5xx']),
            'request_count' => fake()->numberBetween(1, 100),
            'weighted_units' => fake()->numberBetween(1, 100),
        ];
    }

    public function forInboundSource(int $sourceId): static
    {
        return $this->state(fn () => [
            'channel' => MeteringChannel::WebhookReceived->value,
            'inbound_webhook_source_id' => $sourceId,
            'key' => fake()->slug(),
        ]);
    }
}
