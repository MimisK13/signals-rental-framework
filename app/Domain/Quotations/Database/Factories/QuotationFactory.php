<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Database\Factories;

use App\Domain\Members\Models\Member;
use App\Domain\Numbering\Services\SequenceAllocator;
use App\Domain\Projects\Models\Project;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Quotation> */
final class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+2 weeks');

        return [
            'id' => app(SequenceAllocator::class)->next('quotations'),
            'state_id' => snowflake_id(),
            'project_id' => null,
            'member_id' => Member::factory()->organisation(),
            'venue_id' => null,
            'store_id' => Store::factory(),
            'owned_by' => null,
            'number' => 'QUO-'.fake()->unique()->numerify('####'),
            'subject' => fake()->words(3, true),
            'status' => QuotationStatus::Draft,
            'starts_at' => $startsAt,
            'ends_at' => fake()->dateTimeBetween($startsAt, '+4 weeks'),
            'currency_code' => 'EUR',
            'exchange_rate' => '1',
            'prices_include_tax' => false,
            'active_version_id' => null,
            'accepted_version_id' => null,
            'version_count' => 0,
            'editor_revision' => 1,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (): array => [
            'project_id' => $project->id,
            'member_id' => $project->member_id,
            'venue_id' => $project->venue_id,
            'owned_by' => $project->owned_by,
        ]);
    }
}
