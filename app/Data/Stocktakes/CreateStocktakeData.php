<?php

namespace App\Data\Stocktakes;

use Spatie\LaravelData\Data;

class CreateStocktakeData extends Data
{
    public function __construct(
        public int $store_id,
        public ?string $notes = null,
    ) {}
}
