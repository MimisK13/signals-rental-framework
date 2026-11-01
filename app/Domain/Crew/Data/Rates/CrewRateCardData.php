<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Rates;

use App\Domain\Crew\Models\CrewRateCard;
use Spatie\LaravelData\Data;

final class CrewRateCardData extends Data
{
    public function __construct(
        public int $id,
        public string $currency_code,
        public string $effective_from,
        public ?string $effective_to,
        public ?int $store_id,
        public ?int $crew_member_id,
        public ?int $crew_role_id,
        public ?int $hourly_rate_minor,
        public ?int $daily_rate_minor,
        public ?int $fixed_rate_minor,
        public bool $is_active,
    ) {}

    public static function fromModel(CrewRateCard $card): self
    {
        return new self($card->id, $card->currency_code, $card->effective_from->toDateString(), $card->effective_to?->toDateString(), $card->store_id, $card->crew_member_id, $card->crew_role_id, $card->hourly_rate_minor, $card->daily_rate_minor, $card->fixed_rate_minor, $card->is_active);
    }
}
