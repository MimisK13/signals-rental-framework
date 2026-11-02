<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Quotations\Data\QuotationVersionData;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\Events\QuotationVersionActivated;
use App\Domain\Quotations\Verbs\Events\QuotationVersionDeactivated;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ActivateQuotationVersion
{
    use CommitsVerbsEvents;

    public function __invoke(Quotation $quotation, QuotationVersion $version): QuotationVersionData
    {
        Gate::authorize('update', $quotation);

        $this->commitVerbs(function () use ($quotation, $version): void {
            $lockedQuotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $lockedVersion = QuotationVersion::query()->lockForUpdate()->findOrFail($version->id);
            $this->assertBelongsToQuotation($lockedQuotation, $lockedVersion);

            if ($lockedVersion->is_active) {
                return;
            }

            if ($lockedQuotation->activeVersion !== null) {
                QuotationVersionDeactivated::fire(version_id: $lockedQuotation->activeVersion->state_id);
            }

            $quotationStatus = $lockedVersion->status === QuotationVersionStatus::Sent
                ? QuotationStatus::Sent
                : QuotationStatus::Draft;

            QuotationVersionActivated::fire(
                version_pk: $lockedVersion->id,
                version_id: $lockedVersion->state_id,
                quotation_id: $lockedQuotation->state_id,
                quotation_status: $quotationStatus->value,
                charge_excluding_tax_total: $lockedVersion->charge_excluding_tax_total,
                tax_total: $lockedVersion->tax_total,
                charge_including_tax_total: $lockedVersion->charge_including_tax_total,
                charge_total: $lockedVersion->charge_total,
            );
        });

        return QuotationVersionData::fromModel($version->refresh());
    }

    private function assertBelongsToQuotation(Quotation $quotation, QuotationVersion $version): void
    {
        if ($version->quotation_id !== $quotation->id) {
            throw ValidationException::withMessages([
                'version_id' => 'The version does not belong to this quotation.',
            ]);
        }
    }
}
