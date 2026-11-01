<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Expenses;

use Spatie\LaravelData\Data;

final class CreateCrewExpenseData extends Data
{
    public function __construct(
        public int $store_id,
        public int $project_id,
        public string $category,
        public string $incurred_on,
        public string $description,
        public int $amount_minor,
        public string $currency_code,
        public string $exchange_rate,
        public ?int $crew_assignment_id = null,
        public bool $billable_to_client = false,
        public ?string $markup_percent = null,
    ) {}

}
