<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'document_template_id' => DocumentTemplate::factory(),
            'generated_by_user_id' => null,
            'document_type' => 'quote',
            'document_number' => 'Q-'.fake()->unique()->numerify('####'),
            'documentable_type' => Opportunity::class,
            'documentable_id' => Opportunity::factory(),
            'pdf_path' => null,
            'html_snapshot_path' => null,
            'public_token' => null,
            'public_expires_at' => null,
            'public_pin_hash' => null,
            'status' => DocumentStatus::Draft,
            'rendered_data' => ['subject' => fake()->sentence()],
            'locale' => 'en',
            'currency_code' => 'GBP',
            'template_version' => 1,
            'emailed_at' => null,
            'emailed_to' => null,
            'first_viewed_at' => null,
        ];
    }

    public function finalised(): static
    {
        return $this->state(fn (): array => ['status' => DocumentStatus::Final]);
    }

    public function shared(): static
    {
        return $this->state(fn (): array => [
            'public_token' => Str::random(64),
            'public_expires_at' => now()->addDays(30),
        ]);
    }

    public function forOpportunity(Opportunity $opportunity): static
    {
        return $this->state(fn (): array => [
            'documentable_type' => Opportunity::class,
            'documentable_id' => $opportunity->id,
            'store_id' => $opportunity->store_id ?? Store::factory(),
        ]);
    }

    public function generatedBy(User $user): static
    {
        return $this->state(fn (): array => ['generated_by_user_id' => $user->id]);
    }
}
