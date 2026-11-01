<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Expenses;

use Spatie\LaravelData\Data;

final class MarkReimbursedData extends Data
{
    public function __construct(
        public string $finance_reference,
    ) {}

}
