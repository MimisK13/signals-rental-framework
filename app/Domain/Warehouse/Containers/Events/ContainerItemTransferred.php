<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Events;

use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Models\ContainerItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Plugin seam: `container.after_transfer` (serialised-containers.md §Plugin Interaction).
 */
final class ContainerItemTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $sourceContainer,
        public Container $targetContainer,
        public ContainerItem $item,
    ) {}
}
