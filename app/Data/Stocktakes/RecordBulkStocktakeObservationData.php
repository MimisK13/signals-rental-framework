<?php

namespace App\Data\Stocktakes;

use Spatie\LaravelData\Data;

class RecordBulkStocktakeObservationData extends Data
{
    public function __construct(
        public string $counted_quantity,
        public ?string $notes = null,
    ) {}
}
