<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Data;

use Spatie\LaravelData\Data;

/**
 * Successful kit/hybrid container return summary.
 */
final class KitContainerReturnResultData extends Data
{
    /**
     * @param  list<int>  $assets_returned_ids
     */
    public function __construct(
        public ContainerData $container,
        public int $opportunity_id,
        public int $assets_returned,
        public array $assets_returned_ids,
        public bool $verification_required,
    ) {}
}
