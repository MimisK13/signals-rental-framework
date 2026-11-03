<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Database\Factories;

use App\Domain\Notifications\Models\NotificationSetting;
use App\Domain\Notifications\Models\NotificationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationSetting>
 */
class NotificationSettingFactory extends Factory
{
    protected $model = NotificationSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notification_type_id' => NotificationType::factory(),
            'channels' => ['database', 'mail'],
            'channel_config' => [
                'mail' => ['provider_id' => null, 'template_id' => null],
            ],
            'recipient_rules' => [['type' => 'subject_owner']],
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
