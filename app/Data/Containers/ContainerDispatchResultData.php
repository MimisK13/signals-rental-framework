<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Successful transport container dispatch summary (200 response body).
 */
class ContainerDispatchResultData extends Data
{
    /**
     * @param  list<int>  $assets_dispatched
     * @param  list<array<string, mixed>>  $auto_added_products
     * @param  list<array<string, mixed>>  $amended_products
     */
    public function __construct(
        public ContainerData $container,
        public int $opportunity_id,
        public int $assets_checked_out,
        public array $assets_dispatched,
        public array $auto_added_products,
        public array $amended_products,
        public int $containers_dissolved,
    ) {}
}
