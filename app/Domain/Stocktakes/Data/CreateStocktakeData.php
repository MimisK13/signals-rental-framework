<?php

declare(strict_types=1);

namespace App\Domain\Stocktakes\Data;

use Spatie\LaravelData\Data;

final class CreateStocktakeData extends Data
{
    public function __construct(
        public int $store_id,
        public ?string $notes = null,
    ) {}
}
