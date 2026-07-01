<?php

namespace App\Data\Api;

use Spatie\LaravelData\Data;

class ApiUsageData extends Data
{
    public function __construct(
        public string $key,
        public ?string $label,
        public int $request_count,
        public int $weighted_units,
    ) {}

    /**
     * @param  array{key: string, label: string|null, request_count: int, weighted_units: int}  $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            key: $row['key'],
            label: $row['label'],
            request_count: $row['request_count'],
            weighted_units: $row['weighted_units'],
        );
    }
}
