<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Pack outcome including optional informational notices (sub-hire context).
 */
class PackContainerItemResultData extends Data
{
    /**
     * @param  list<array<string, mixed>>  $notices
     */
    public function __construct(
        public ContainerItemData $container_item,
        public array $notices = [],
    ) {}
}
