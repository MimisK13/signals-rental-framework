<?php

namespace Database\Factories;

use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    protected $model = DocumentTemplate::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'store_id' => Store::factory(),
            'created_by_member_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'document_type' => 'quote',
            'model_type' => Opportunity::class,
            'body_html' => '<p>{{ $opportunity->subject }}</p>',
            'header_html' => null,
            'footer_html' => null,
            'styles_css' => null,
            'page_config' => [
                'page_size' => 'A4',
                'page_orientation' => 'portrait',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            ],
            'branding_config' => null,
            'is_default' => false,
            'is_system' => false,
            'is_active' => true,
            'version' => 1,
            'last_edited_at' => now(),
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    public function forMember(Member $member): static
    {
        return $this->state(fn (): array => ['created_by_member_id' => $member->id]);
    }
}
