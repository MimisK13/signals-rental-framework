<?php

declare(strict_types=1);

namespace App\Domain\Activities\Database\Factories;

use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityParticipant;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityParticipant>
 */
final class ActivityParticipantFactory extends Factory
{
    protected $model = ActivityParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'member_id' => Member::factory(),
            'mute' => false,
        ];
    }
}
