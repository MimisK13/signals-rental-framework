<?php

namespace App\Events\Containers;

use App\Models\Container;
use App\Models\ContainerItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Plugin seam: `container.after_pack` (serialised-containers.md §Plugin Interaction).
 */
class ContainerPacked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public ContainerItem $item,
    ) {}
}
