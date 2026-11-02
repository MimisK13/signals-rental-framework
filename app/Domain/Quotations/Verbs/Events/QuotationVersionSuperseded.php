<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\Events;

use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\States\QuotationVersionState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

final class QuotationVersionSuperseded extends Event
{
    public function __construct(
        #[StateId(QuotationVersionState::class)]
        public int $version_id,
        public int $superseded_by_version_id,
    ) {}

    public function apply(QuotationVersionState $state): void
    {
        if (! in_array($state->status, [QuotationVersionStatus::Accepted->value, QuotationVersionStatus::Declined->value], true)) {
            $state->status = QuotationVersionStatus::Superseded->value;
        }

        $state->superseded_by_version_id = $this->superseded_by_version_id;
        $state->is_active = false;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(QuotationVersionState $state): void
    {
        $version = QuotationVersion::query()->where('state_id', $state->id)->first();

        if ($version === null) {
            return;
        }

        $attributes = [
            'superseded_by_version_id' => $state->superseded_by_version_id,
            'is_active' => false,
        ];

        if (! in_array($version->status, [QuotationVersionStatus::Accepted, QuotationVersionStatus::Declined], true)) {
            $attributes['status'] = QuotationVersionStatus::Superseded->value;
        }

        $version->update($attributes);
    }
}
