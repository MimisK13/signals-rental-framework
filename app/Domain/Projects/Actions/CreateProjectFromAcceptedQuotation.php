<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Data\CreateProjectFromQuotationData;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;

/**
 * Internal cross-domain boundary used only as the transactional consequence of
 * accepting a standalone quotation. User authorization belongs to acceptance.
 */
final class CreateProjectFromAcceptedQuotation
{
    public function __invoke(CreateProjectFromQuotationData $data): Project
    {
        return Project::query()->create([
            'name' => $data->name,
            'member_id' => $data->member_id,
            'venue_id' => $data->venue_id,
            'owned_by' => $data->owned_by,
            'status' => ProjectStatus::Active,
            'starts_at' => $data->starts_at,
            'ends_at' => $data->ends_at,
        ]);
    }
}
