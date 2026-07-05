<?php

namespace Database\Factories;

use App\Models\CommunicationTemplate;
use App\Models\NotificationType;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunicationTemplate>
 */
class CommunicationTemplateFactory extends Factory
{
    protected $model = CommunicationTemplate::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'notification_type_id' => NotificationType::factory(),
            'store_id' => Store::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'channel' => 'email',
            'subject' => 'Hello {{ contact.name | default:"there" }}',
            'body' => 'Your reference is **{{ opportunity.reference }}**.',
            'attachments_config' => null,
            'is_default' => false,
            'is_system' => false,
            'is_active' => true,
            'version' => 1,
            'last_edited_at' => now(),
        ];
    }

    public function email(): static
    {
        return $this->state(fn (): array => ['channel' => 'email']);
    }

    public function sms(): static
    {
        return $this->state(fn (): array => [
            'channel' => 'sms',
            'subject' => null,
            'body' => 'Ref {{ opportunity.reference }}',
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (): array => [
            'is_system' => true,
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
