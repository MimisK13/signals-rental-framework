<?php

declare(strict_types=1);

namespace App\Domain\Stocktakes\Data;

use Spatie\LaravelData\Data;

final class RecordBulkStocktakeObservationData extends Data
{
    public function __construct(
        public string $counted_quantity,
        public ?string $notes = null,
    ) {}
}
