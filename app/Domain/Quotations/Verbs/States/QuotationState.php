<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\States;

use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

final class QuotationState extends State
{
    public int $quotation_id = 0;

    public ?int $project_id = null;

    public int $member_id = 0;

    public ?int $venue_id = null;

    public int $store_id = 0;

    public ?int $owned_by = null;

    public string $number = '';

    public string $subject = '';

    public ?string $reference = null;

    public ?string $description = null;

    public string $status = 'draft';

    public ?CarbonImmutable $starts_at = null;

    public ?CarbonImmutable $ends_at = null;

    public ?CarbonImmutable $valid_until = null;

    public string $currency_code = '';

    public string $exchange_rate = '1';

    public bool $prices_include_tax = false;

    public int $active_version_id = 0;

    public ?int $accepted_version_id = null;

    public int $version_count = 0;

    public int $charge_excluding_tax_total = 0;

    public int $tax_total = 0;

    public int $charge_including_tax_total = 0;

    public int $charge_total = 0;

    public ?int $deal_total = null;

    public string $deal_price_mode = 'flat_headline';

    public ?int $deal_price_original_total = null;

    public int $editor_revision = 1;

    public ?CarbonImmutable $accepted_at = null;

    public ?int $accepted_by = null;

    public ?int $created_by = null;

    public ?CarbonImmutable $last_event_at = null;
}
