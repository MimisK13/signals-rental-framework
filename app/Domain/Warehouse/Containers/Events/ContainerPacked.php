<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Events;

use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Models\ContainerItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Plugin seam: `container.after_pack` (serialised-containers.md §Plugin Interaction).
 */
final class ContainerPacked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public ContainerItem $item,
    ) {}
}
