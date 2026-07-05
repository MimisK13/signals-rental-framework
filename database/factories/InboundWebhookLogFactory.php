<?php

namespace Database\Factories;

use App\Models\InboundWebhookLog;
use App\Models\InboundWebhookSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InboundWebhookLog>
 */
class InboundWebhookLogFactory extends Factory
{
    protected $model = InboundWebhookLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inbound_webhook_source_id' => InboundWebhookSource::factory(),
            'event' => 'order.created',
            'headers' => ['Content-Type' => 'application/json'],
            'payload' => ['id' => fake()->randomNumber(4)],
            'signature_valid' => true,
            'status_code' => 200,
            'request_id' => (string) Str::uuid(),
            'price_weight' => 1,
            'weighted_units' => 1,
        ];
    }

    public function invalidSignature(): static
    {
        return $this->state(fn () => [
            'signature_valid' => false,
            'status_code' => 401,
        ]);
    }
}
