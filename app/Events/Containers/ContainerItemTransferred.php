<?php

namespace App\Events\Containers;

use App\Models\Container;
use App\Models\ContainerItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Plugin seam: `container.after_transfer` (serialised-containers.md §Plugin Interaction).
 */
class ContainerItemTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $sourceContainer,
        public Container $targetContainer,
        public ContainerItem $item,
    ) {}
}
