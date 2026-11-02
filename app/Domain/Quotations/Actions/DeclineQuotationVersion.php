<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Quotations\Data\DeclineQuotationVersionData;
use App\Domain\Quotations\Data\QuotationVersionData;
use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationAudit;
use App\Domain\Quotations\Verbs\Events\QuotationVersionDeclined;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeclineQuotationVersion
{
    use CommitsVerbsEvents;

    public function __construct(private readonly QuotationAudit $audit) {}

    public function __invoke(Quotation $quotation, QuotationVersion $version, DeclineQuotationVersionData $data): QuotationVersionData
    {
        Gate::authorize('update', $quotation);
        $wasDeclined = $version->status === QuotationVersionStatus::Declined;

        $this->commitVerbs(function () use ($quotation, $version, $data): void {
            $lockedQuotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $lockedVersion = QuotationVersion::query()->lockForUpdate()->findOrFail($version->id);

            if ($lockedVersion->quotation_id !== $lockedQuotation->id) {
                throw ValidationException::withMessages([
                    'version_id' => 'The version does not belong to this quotation.',
                ]);
            }

            if ($lockedVersion->status === QuotationVersionStatus::Declined) {
                return;
            }

            if ($lockedVersion->is_active && $this->hasAnotherLiveVersion($lockedQuotation, $lockedVersion)) {
                throw ValidationException::withMessages([
                    'version_id' => 'Activate another live version before declining the active version.',
                ]);
            }

            QuotationVersionDeclined::fire(
                version_id: $lockedVersion->state_id,
                quotation_id: $lockedQuotation->state_id,
                reason: $data->reason,
                declined_at: CarbonImmutable::now()->toIso8601String(),
                was_active: $lockedVersion->is_active,
            );
        });

        $result = QuotationVersionData::fromModel($version->refresh());
        if (! $wasDeclined) {
            $this->audit->record($quotation->refresh(), 'quotation.declined', ['version_id' => $version->id]);
        }

        return $result;
    }

    private function hasAnotherLiveVersion(Quotation $quotation, QuotationVersion $version): bool
    {
        return $quotation->versions()
            ->where('id', '!=', $version->id)
            ->whereIn('status', [QuotationVersionStatus::Draft->value, QuotationVersionStatus::Sent->value])
            ->exists();
    }
}
