<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Outcome of a single repack-on-return scan (return with optional pack).
 */
class RepackReturnItemResultData extends Data
{
    /**
     * @param  list<array<string, mixed>>  $notices
     */
    public function __construct(
        public int $opportunity_id,
        public int $serialised_item_id,
        public bool $packed,
        public ?ContainerItemData $container_item = null,
        public array $notices = [],
    ) {}
}
