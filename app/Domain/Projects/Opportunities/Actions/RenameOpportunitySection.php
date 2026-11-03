<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Domain\Projects\Opportunities\Data\OpportunitySectionData;
use App\Domain\Projects\Opportunities\Data\RenameOpportunitySectionData;
use App\Domain\Projects\Opportunities\Models\OpportunitySection;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Renames a custom line-item grouping (section).
 *
 * Plain Eloquent update — sections are not event-sourced (M8-3 grouping
 * decision). The before/after name is captured for the audit trail.
 */
class RenameOpportunitySection
{
    public function __invoke(OpportunitySection $section, RenameOpportunitySectionData $data): OpportunitySectionData
    {
        Gate::authorize('opportunities.edit');

        return DB::transaction(function () use ($section, $data): OpportunitySectionData {
            $oldName = $section->name;

            $section->update(['name' => $data->name]);

            event(new AuditableEvent(
                $section,
                'opportunity_section.renamed',
                oldValues: ['name' => $oldName],
                newValues: ['name' => $section->name],
            ));

            return OpportunitySectionData::fromModel($section);
        });
    }
}
