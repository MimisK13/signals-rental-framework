<?php

declare(strict_types=1);

namespace App\Domain\Communications\Database\Factories;

use App\Domain\Communications\Models\ChannelProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelProvider>
 */
final class ChannelProviderFactory extends Factory
{
    protected $model = ChannelProvider::class;

    public function definition(): array
    {
        return [
            'channel' => 'fake_channel',
            'driver' => 'fake_plugin',
            'name' => fake()->words(2, true),
            'config' => ['api_key' => 'secret-key-value'],
            'is_default' => true,
            'is_active' => true,
            'last_test_status' => null,
            'last_test_message' => null,
            'last_tested_at' => null,
        ];
    }

    public function tested(): static
    {
        return $this->state(fn (): array => [
            'last_test_status' => 'passed',
            'last_test_message' => 'Test succeeded.',
            'last_tested_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'last_test_status' => 'failed',
            'last_test_message' => 'Test failed.',
            'last_tested_at' => now(),
        ]);
    }
}
