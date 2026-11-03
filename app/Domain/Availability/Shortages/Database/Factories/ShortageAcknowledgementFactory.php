<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Database\Factories;

use App\Domain\Availability\Shortages\Enums\ShortagePolicy;
use App\Domain\Availability\Shortages\Models\ShortageAcknowledgement;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShortageAcknowledgement>
 */
class ShortageAcknowledgementFactory extends Factory
{
    protected $model = ShortageAcknowledgement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'user_id' => null,
            'acknowledged_at' => now(),
            'policy_at_time' => ShortagePolicy::Block->value,
            'permission_used' => false,
            'shortages_snapshot' => [],
            'notes' => null,
        ];
    }
}
