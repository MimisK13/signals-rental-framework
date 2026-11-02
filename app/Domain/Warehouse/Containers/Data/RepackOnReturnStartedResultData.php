<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Data;

use Spatie\LaravelData\Data;

/**
 * Successful repack-on-return initiation summary.
 */
final class RepackOnReturnStartedResultData extends Data
{
    public function __construct(
        public ContainerData $container,
        public int $opportunity_id,
        public ?int $previous_container_id,
        public int $housing_asset_returned_id,
    ) {}
}
