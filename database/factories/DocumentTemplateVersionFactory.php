<?php

namespace Database\Factories;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTemplateVersion>
 */
class DocumentTemplateVersionFactory extends Factory
{
    protected $model = DocumentTemplateVersion::class;

    public function definition(): array
    {
        return [
            'document_template_id' => DocumentTemplate::factory(),
            'edited_by_member_id' => null,
            'version' => 1,
            'body_html' => '<p>Version body</p>',
            'header_html' => null,
            'footer_html' => null,
            'styles_css' => null,
            'page_config' => [
                'page_size' => 'A4',
                'page_orientation' => 'portrait',
            ],
            'change_note' => fake()->optional()->sentence(),
        ];
    }

    public function forMember(Member $member): static
    {
        return $this->state(fn (): array => ['edited_by_member_id' => $member->id]);
    }
}
