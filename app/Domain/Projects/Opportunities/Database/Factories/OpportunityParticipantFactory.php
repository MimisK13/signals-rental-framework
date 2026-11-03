<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Database\Factories;

use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityParticipant;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityParticipant>
 */
class OpportunityParticipantFactory extends Factory
{
    protected $model = OpportunityParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'member_id' => Member::factory()->contact(),
            'role' => $this->faker->randomElement([
                'Primary contact',
                'Secondary contact',
                'Account manager',
                'Site contact',
            ]),
            'mute' => false,
        ];
    }

    /**
     * The participant is muted (opted out of opportunity notifications).
     */
    public function muted(): static
    {
        return $this->state(fn (): array => ['mute' => true]);
    }
}
