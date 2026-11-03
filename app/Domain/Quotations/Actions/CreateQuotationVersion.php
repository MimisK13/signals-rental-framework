<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Numbering\Services\SequenceAllocator;
use App\Domain\Quotations\Data\CreateQuotationVersionData;
use App\Domain\Quotations\Data\QuotationVersionData;
use App\Domain\Quotations\Enums\QuotationVersionType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\Events\QuotationVersionCreated;
use App\Domain\Quotations\Verbs\Events\QuotationVersionDeactivated;
use App\Domain\Quotations\Verbs\Events\QuotationVersionSuperseded;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateQuotationVersion
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly SequenceAllocator $sequenceAllocator,
        private readonly CloneQuotationVersionItems $cloneItems,
    ) {}

    public function __invoke(Quotation $quotation, CreateQuotationVersionData $data): QuotationVersionData
    {
        Gate::authorize('update', $quotation);

        try {
            $versionId = DB::transaction(function () use ($quotation, $data): int {
                $created = $this->commitVerbs(function () use ($quotation, $data): array {
                    $lockedQuotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);

                    if ($lockedQuotation->status->isTerminal()) {
                        throw ValidationException::withMessages([
                            'quotation' => 'A terminal quotation cannot receive another version.',
                        ]);
                    }

                    $source = $this->resolveSource($lockedQuotation, $data->source_version_id);
                    $versionType = QuotationVersionType::from($data->version_type);
                    $newVersionId = $this->sequenceAllocator->next('quotation_versions');
                    $versionNumber = $lockedQuotation->version_count + 1;

                    if ($lockedQuotation->activeVersion !== null) {
                        QuotationVersionDeactivated::fire(version_id: $lockedQuotation->activeVersion->state_id);
                    }

                    QuotationVersionCreated::fire(
                        version_pk: $newVersionId,
                        quotation_pk: $lockedQuotation->id,
                        version_number: $versionNumber,
                        quotation_id: $lockedQuotation->state_id,
                        version_type: $versionType->value,
                        parent_version_id: $versionType === QuotationVersionType::Revision ? $source?->id : null,
                        label: $data->label,
                        notes: $data->notes,
                        created_by: Auth::id(),
                        currency_code: $lockedQuotation->currency_code,
                        exchange_rate: $lockedQuotation->exchange_rate,
                        prices_include_tax: $lockedQuotation->prices_include_tax,
                    );

                    if ($versionType === QuotationVersionType::Revision && $source !== null) {
                        QuotationVersionSuperseded::fire(
                            version_id: $source->state_id,
                            superseded_by_version_id: $newVersionId,
                        );
                    }

                    return ['version_id' => $newVersionId, 'source_id' => $source?->id];
                });

                if ($created['source_id'] !== null) {
                    ($this->cloneItems)(
                        Quotation::query()->findOrFail($quotation->id),
                        QuotationVersion::query()->findOrFail($created['source_id']),
                        QuotationVersion::query()->findOrFail($created['version_id']),
                    );
                }

                return $created['version_id'];
            });
        } catch (Throwable $exception) {
            $this->resetVerbsState();

            throw $exception;
        }

        return QuotationVersionData::fromModel(
            QuotationVersion::query()->findOrFail($versionId),
        );
    }

    private function resolveSource(Quotation $quotation, ?int $sourceVersionId): ?QuotationVersion
    {
        $sourceVersionId ??= $quotation->active_version_id;

        if ($sourceVersionId === null) {
            return null;
        }

        $source = QuotationVersion::query()
            ->whereKey($sourceVersionId)
            ->whereBelongsTo($quotation)
            ->first();

        if ($source === null) {
            throw ValidationException::withMessages([
                'source_version_id' => 'The source version does not belong to this quotation.',
            ]);
        }

        return $source;
    }
}
