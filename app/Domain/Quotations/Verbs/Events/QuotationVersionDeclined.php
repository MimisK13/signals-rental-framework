<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\Events;

use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\States\QuotationState;
use App\Domain\Quotations\Verbs\States\QuotationVersionState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

final class QuotationVersionDeclined extends Event
{
    public function __construct(
        #[StateId(QuotationVersionState::class)]
        public int $version_id,
        #[StateId(QuotationState::class)]
        public int $quotation_id,
        public ?string $reason,
        public string $declined_at,
        public bool $was_active,
    ) {}

    public function validate(QuotationVersionState $versionState, QuotationState $quotationState): void
    {
        $this->assert(
            in_array($versionState->status, [QuotationVersionStatus::Draft->value, QuotationVersionStatus::Sent->value], true),
            'Only a draft or sent version can be declined.',
        );
        $this->assert(
            ! QuotationStatus::from($quotationState->status)->isTerminal(),
            'A terminal quotation cannot be declined.',
        );
    }

    public function applyToVersion(QuotationVersionState $state): void
    {
        $state->status = QuotationVersionStatus::Declined->value;
        $state->declined_at = CarbonImmutable::parse($this->declined_at);
        $state->decline_reason = $this->reason;
        $state->is_active = false;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function applyToQuotation(QuotationState $state): void
    {
        if ($this->was_active) {
            $state->status = QuotationStatus::Declined->value;
            $state->active_version_id = 0;
        }

        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(QuotationVersionState $versionState, QuotationState $quotationState): void
    {
        QuotationVersion::query()->where('state_id', $versionState->id)->update([
            'status' => $versionState->status,
            'declined_at' => $versionState->declined_at,
            'decline_reason' => $versionState->decline_reason,
            'is_active' => false,
        ]);

        if ($this->was_active) {
            Quotation::query()->where('state_id', $quotationState->id)->update([
                'status' => $quotationState->status,
                'active_version_id' => null,
            ]);
        }
    }
}
