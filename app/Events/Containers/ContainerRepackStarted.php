<?php

namespace App\Events\Containers;

use App\Models\Container;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Plugin seam: `container.after_repack` (serialised-containers.md §Plugin Interaction).
 *
 * Fired when a repack-on-return session is initiated and the new open container exists.
 */
class ContainerRepackStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public int $opportunityId,
        public ?int $previousContainerId,
    ) {}
}
