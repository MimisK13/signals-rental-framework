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

final class QuotationVersionSent extends Event
{
    public function __construct(
        #[StateId(QuotationVersionState::class)]
        public int $version_id,
        #[StateId(QuotationState::class)]
        public int $quotation_id,
        public ?int $sent_to,
        public string $sent_via,
        public string $sent_at,
    ) {}

    public function validate(QuotationVersionState $versionState, QuotationState $quotationState): void
    {
        $this->assert($versionState->is_active, 'Only the active version can be sent.');
        $this->assert($versionState->status === QuotationVersionStatus::Draft->value, 'Only a draft version can be sent.');
        $this->assert(
            ! QuotationStatus::from($quotationState->status)->isTerminal(),
            'A terminal quotation cannot be sent.',
        );
    }

    public function applyToVersion(QuotationVersionState $state): void
    {
        $state->status = QuotationVersionStatus::Sent->value;
        $state->sent_to = $this->sent_to;
        $state->sent_via = $this->sent_via;
        $state->sent_at = CarbonImmutable::parse($this->sent_at);
        $state->last_event_at = CarbonImmutable::now();
    }

    public function applyToQuotation(QuotationState $state): void
    {
        $state->status = QuotationStatus::Sent->value;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(QuotationVersionState $versionState, QuotationState $quotationState): void
    {
        QuotationVersion::query()->where('state_id', $versionState->id)->update([
            'status' => $versionState->status,
            'sent_to' => $versionState->sent_to,
            'sent_via' => $versionState->sent_via,
            'sent_at' => $versionState->sent_at,
        ]);
        Quotation::query()->where('state_id', $quotationState->id)->update(['status' => $quotationState->status]);
    }
}
