<?php

declare(strict_types=1);

namespace App\Domain\Projects\Data;

use App\Domain\Projects\Models\Project;
use App\Domain\Quotations\Data\QuotationData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

final class ProjectData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $member_id,
        public ?int $venue_id,
        public ?int $owned_by,
        public string $status,
        public ?string $starts_at,
        public ?string $ends_at,
        public int $opportunities_count = 0,
        public int $quotations_count = 0,
        /** @var Lazy|list<QuotationData> */
        public Lazy|array $quotations = [],
    ) {}

    public static function fromModel(Project $project): self
    {
        return new self(
            id: $project->id,
            name: $project->name,
            member_id: $project->member_id,
            venue_id: $project->venue_id,
            owned_by: $project->owned_by,
            status: $project->status->value,
            starts_at: $project->starts_at?->toIso8601String(),
            ends_at: $project->ends_at?->toIso8601String(),
            opportunities_count: (int) ($project->opportunities_count ?? $project->opportunities()->count()),
            quotations_count: (int) ($project->quotations_count ?? $project->quotations()->count()),
            quotations: Lazy::whenLoaded(
                'quotations',
                $project,
                fn (): array => $project->quotations->map(
                    fn ($quotation): QuotationData => QuotationData::fromModel($quotation),
                )->values()->all(),
            ),
        );
    }
}
