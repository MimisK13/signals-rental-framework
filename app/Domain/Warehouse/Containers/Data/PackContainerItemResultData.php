<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Data;

use Spatie\LaravelData\Data;

/**
 * Pack outcome including optional informational notices (sub-hire context).
 */
final class PackContainerItemResultData extends Data
{
    /**
     * @param  list<array<string, mixed>>  $notices
     */
    public function __construct(
        public ContainerItemData $container_item,
        public array $notices = [],
    ) {}
}
