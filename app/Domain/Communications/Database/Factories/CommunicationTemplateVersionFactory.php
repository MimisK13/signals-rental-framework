<?php

declare(strict_types=1);

namespace App\Domain\Communications\Database\Factories;

use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Models\CommunicationTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationTemplateVersion>
 */
final class CommunicationTemplateVersionFactory extends Factory
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
