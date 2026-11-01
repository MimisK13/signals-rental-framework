<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Timesheets;

use Spatie\LaravelData\Data;

final class ExportToFinanceData extends Data
{
    public function __construct(
        public string $finance_reference,
        public ?int $version = null,
    ) {}

}
