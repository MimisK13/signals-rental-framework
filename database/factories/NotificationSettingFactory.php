<?php

namespace Database\Factories;

use App\Models\NotificationSetting;
use App\Models\NotificationType;
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
