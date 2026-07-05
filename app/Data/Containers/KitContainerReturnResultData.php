<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Successful kit/hybrid container return summary.
 */
class KitContainerReturnResultData extends Data
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
