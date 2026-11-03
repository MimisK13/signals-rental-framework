<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Members\Models\Member;
use App\Domain\Projects\Actions\CreateProjectFromAcceptedQuotation;
use App\Domain\Projects\Data\CreateProjectFromQuotationData;
use App\Domain\Projects\Models\Project;
use App\Domain\Quotations\Actions\Concerns\AssertsQuotationCompleteness;
use App\Domain\Quotations\Data\AcceptQuotationVersionData;
use App\Domain\Quotations\Data\QuotationAcceptanceData;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationAudit;
use App\Domain\Quotations\Verbs\Events\QuotationVersionAccepted;
use App\Domain\Quotations\Verbs\Events\QuotationVersionSuperseded;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AcceptQuotationVersion
{
    use AssertsQuotationCompleteness, CommitsVerbsEvents;

    public function __construct(
        private readonly CreateProjectFromAcceptedQuotation $createProject,
        private readonly QuotationAudit $audit,
    ) {}

    public function __invoke(Quotation $quotation, QuotationVersion $version, AcceptQuotationVersionData $data): QuotationAcceptanceData
    {
        Gate::authorize('accept', $quotation);
        $wasAccepted = $quotation->status === QuotationStatus::Accepted;

        $projectId = $this->commitVerbs(function () use ($quotation, $version, $data): int {
            $lockedQuotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $lockedVersion = QuotationVersion::query()->lockForUpdate()->findOrFail($version->id);

            if ($lockedQuotation->status === QuotationStatus::Accepted) {
                return $this->resolveIdempotentProjectId($lockedQuotation, $lockedVersion);
            }

            $this->assertActiveVersion($lockedQuotation, $lockedVersion);
            $this->assertReadyForCustomer($lockedQuotation, $lockedVersion);

            $acceptedBy = $data->accepted_by ?? $lockedQuotation->member_id;
            Member::query()->findOrFail($acceptedBy);
            $projectId = $this->resolveProjectId($lockedQuotation);

            $otherVersions = $lockedQuotation->versions()
                ->where('id', '!=', $lockedVersion->id)
                ->whereNotIn('status', [
                    QuotationVersionStatus::Accepted->value,
                    QuotationVersionStatus::Declined->value,
                    QuotationVersionStatus::Superseded->value,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($otherVersions as $otherVersion) {
                QuotationVersionSuperseded::fire(
                    version_id: $otherVersion->state_id,
                    superseded_by_version_id: $lockedVersion->id,
                );
            }

            QuotationVersionAccepted::fire(
                version_pk: $lockedVersion->id,
                version_id: $lockedVersion->state_id,
                quotation_id: $lockedQuotation->state_id,
                project_id: $projectId,
                accepted_by: $acceptedBy,
                accepted_at: CarbonImmutable::now()->toIso8601String(),
                charge_excluding_tax_total: $lockedVersion->charge_excluding_tax_total,
                tax_total: $lockedVersion->tax_total,
                charge_including_tax_total: $lockedVersion->charge_including_tax_total,
                charge_total: $lockedVersion->charge_total,
            );

            return $projectId;
        });

        $acceptedQuotation = Quotation::query()->findOrFail($quotation->id);
        $acceptedVersion = QuotationVersion::query()->findOrFail($version->id);

        if ($acceptedQuotation->project_id !== $projectId) {
            throw new \LogicException('The accepted quotation was not linked to its Project.');
        }

        $result = QuotationAcceptanceData::fromModels($acceptedQuotation, $acceptedVersion);
        if (! $wasAccepted) {
            $this->audit->record($acceptedQuotation, 'quotation.accepted', ['version_id' => $acceptedVersion->id, 'project_id' => $projectId]);
        }

        return $result;
    }

    private function assertActiveVersion(Quotation $quotation, QuotationVersion $version): void
    {
        if ($version->quotation_id !== $quotation->id || $quotation->active_version_id !== $version->id) {
            throw ValidationException::withMessages([
                'version_id' => 'Only the active quotation version can be accepted.',
            ]);
        }
    }

    private function resolveIdempotentProjectId(Quotation $quotation, QuotationVersion $version): int
    {
        if ($quotation->accepted_version_id !== $version->id || $quotation->project_id === null) {
            throw ValidationException::withMessages([
                'version_id' => 'This quotation was already accepted with a different version.',
            ]);
        }

        return $quotation->project_id;
    }

    private function resolveProjectId(Quotation $quotation): int
    {
        if ($quotation->project_id !== null) {
            return Project::query()->findOrFail($quotation->project_id)->id;
        }

        $project = ($this->createProject)(CreateProjectFromQuotationData::from([
            'name' => $quotation->subject,
            'member_id' => $quotation->member_id,
            'venue_id' => $quotation->venue_id,
            'owned_by' => $quotation->owned_by,
            'starts_at' => $quotation->starts_at?->toIso8601String(),
            'ends_at' => $quotation->ends_at?->toIso8601String(),
        ]));

        return $project->id;
    }
}
