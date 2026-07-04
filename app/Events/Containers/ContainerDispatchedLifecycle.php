<?php

namespace App\Events\Containers;

use App\Models\Container;
use App\Models\Opportunity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Plugin seam: `container.after_dispatch` (serialised-containers.md §Plugin Interaction).
 */
class ContainerDispatchedLifecycle
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public Opportunity $opportunity,
        public int $itemCount,
    ) {}
}
