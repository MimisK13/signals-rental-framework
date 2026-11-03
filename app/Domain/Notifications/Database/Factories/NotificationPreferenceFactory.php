<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Database\Factories;

use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Models\NotificationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'notification_type_id' => NotificationType::factory(),
            'channels' => ['database'],
            'is_muted' => false,
        ];
    }

    public function muted(): static
    {
        return $this->state(fn () => ['is_muted' => true]);
    }
}
