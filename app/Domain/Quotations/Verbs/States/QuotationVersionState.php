<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\States;

use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

final class QuotationVersionState extends State
{
    public int $version_id = 0;

    public int $quotation_id = 0;

    public int $version_number = 0;

    public ?int $parent_version_id = null;

    public ?int $superseded_by_version_id = null;

    public string $version_type = 'original';

    public ?string $label = null;

    public bool $is_active = false;

    public string $status = 'draft';

    public int $charge_excluding_tax_total = 0;

    public int $tax_total = 0;

    public int $charge_including_tax_total = 0;

    public int $charge_total = 0;

    public string $currency_code = '';

    public string $exchange_rate = '1';

    public bool $prices_include_tax = false;

    public ?string $notes = null;

    public ?int $created_by = null;

    public ?CarbonImmutable $sent_at = null;

    public ?int $sent_to = null;

    public ?string $sent_via = null;

    public ?CarbonImmutable $accepted_at = null;

    public ?int $accepted_by = null;

    public ?CarbonImmutable $declined_at = null;

    public ?string $decline_reason = null;

    public ?CarbonImmutable $last_event_at = null;
}
