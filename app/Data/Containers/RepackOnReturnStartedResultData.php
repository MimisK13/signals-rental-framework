<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Successful repack-on-return initiation summary.
 */
class RepackOnReturnStartedResultData extends Data
{
    public function __construct(
        public ContainerData $container,
        public int $opportunity_id,
        public ?int $previous_container_id,
        public int $housing_asset_returned_id,
    ) {}
}
