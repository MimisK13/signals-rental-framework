<?php

namespace Database\Factories;

use App\Enums\MeteringChannel;
use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RequestLog>
 */
class RequestLogFactory extends Factory
{
    protected $model = RequestLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel' => MeteringChannel::Api->value,
            'request_id' => (string) Str::uuid(),
            'method' => fake()->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'uri' => '/api/v1/'.fake()->word(),
            'route_name' => 'api.v1.'.fake()->word().'.index',
            'resource' => fake()->word(),
            'operation' => fake()->randomElement(['index', 'show', 'store', 'update', 'destroy']),
            'personal_access_token_id' => null,
            'user_id' => User::factory(),
            'ability' => fake()->word().':read',
            'status_code' => 200,
            'duration_ms' => fake()->numberBetween(10, 500),
            'request_bytes' => fake()->numberBetween(0, 2048),
            'response_bytes' => fake()->numberBetween(0, 4096),
            'price_weight' => 1,
            'weighted_units' => 1,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'request_body' => null,
            'response_body' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function failed(int $statusCode = 500): static
    {
        return $this->state(fn () => [
            'status_code' => $statusCode,
        ]);
    }
}
