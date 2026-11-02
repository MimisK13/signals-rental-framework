<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\Events;

use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\States\QuotationVersionState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

final class QuotationVersionDeactivated extends Event
{
    public function __construct(
        #[StateId(QuotationVersionState::class)]
        public int $version_id,
    ) {}

    public function apply(QuotationVersionState $state): void
    {
        $state->is_active = false;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(QuotationVersionState $state): void
    {
        QuotationVersion::query()->where('state_id', $state->id)->update(['is_active' => false]);
    }
}
