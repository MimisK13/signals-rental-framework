<?php

namespace App\Events\Containers;

use App\Models\Container;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Plugin seam: `container.after_seal` (serialised-containers.md §Plugin Interaction).
 */
class ContainerSealed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public int $itemCount,
    ) {}
}
