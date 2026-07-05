<?php

namespace Database\Factories;

use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationTemplateVersion>
 */
class CommunicationTemplateVersionFactory extends Factory
{
    protected $model = CommunicationTemplateVersion::class;

    public function definition(): array
    {
        return [
            'communication_template_id' => CommunicationTemplate::factory(),
            'edited_by_member_id' => null,
            'version' => 1,
            'subject' => 'Version subject',
            'body' => 'Version body content.',
            'change_note' => fake()->optional()->sentence(),
        ];
    }
}
