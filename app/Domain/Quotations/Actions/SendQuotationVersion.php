<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Quotations\Actions\Concerns\AssertsQuotationCompleteness;
use App\Domain\Quotations\Data\QuotationVersionData;
use App\Domain\Quotations\Data\SendQuotationVersionData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationAudit;
use App\Domain\Quotations\Verbs\Events\QuotationVersionSent;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SendQuotationVersion
{
    use AssertsQuotationCompleteness, CommitsVerbsEvents;

    public function __construct(private readonly QuotationAudit $audit) {}

    public function __invoke(Quotation $quotation, QuotationVersion $version, SendQuotationVersionData $data): QuotationVersionData
    {
        Gate::authorize('send', $quotation);

        $this->commitVerbs(function () use ($quotation, $version, $data): void {
            $lockedQuotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $lockedVersion = QuotationVersion::query()->lockForUpdate()->findOrFail($version->id);
            $this->assertActiveVersion($lockedQuotation, $lockedVersion);
            $this->assertReadyForCustomer($lockedQuotation, $lockedVersion);

            $recipientId = $data->sent_to ?? $lockedQuotation->member_id;
            Member::query()->findOrFail($recipientId);

            QuotationVersionSent::fire(
                version_id: $lockedVersion->state_id,
                quotation_id: $lockedQuotation->state_id,
                sent_to: $recipientId,
                sent_via: $data->sent_via,
                sent_at: CarbonImmutable::now()->toIso8601String(),
            );
        });

        $result = QuotationVersionData::fromModel($version->refresh());
        $this->audit->record($quotation->refresh(), 'quotation.sent', ['version_id' => $version->id]);

        return $result;
    }

    private function assertActiveVersion(Quotation $quotation, QuotationVersion $version): void
    {
        if ($version->quotation_id !== $quotation->id || $quotation->active_version_id !== $version->id) {
            throw ValidationException::withMessages([
                'version_id' => 'Choose the active version before sending it.',
            ]);
        }
    }
}
