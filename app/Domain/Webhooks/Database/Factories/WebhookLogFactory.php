<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Database\Factories;

use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookLog>
 */
class WebhookLogFactory extends Factory
{
    protected $model = WebhookLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event' => 'user.created',
            'payload' => ['user' => ['id' => 1, 'name' => 'Test']],
            'response_code' => 200,
            'response_body' => 'OK',
            'attempts' => 1,
            'delivered_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'response_code' => 500,
            'response_body' => 'Internal Server Error',
            'delivered_at' => null,
        ]);
    }
}
