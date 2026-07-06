<?php

namespace App\Queries\Api;

class UsageQueryResult
{
    /**
     * @param  list<array{key: string, label: string|null, request_count: int, weighted_units: int}>  $rows
     * @param  array{request_count: int, weighted_units: int}  $totals
     */
    public function __construct(
        public array $rows,
        public array $totals,
    ) {}
}
