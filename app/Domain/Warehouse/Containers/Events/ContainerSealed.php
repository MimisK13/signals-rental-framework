<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Events;

use App\Domain\Warehouse\Containers\Models\Container;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Plugin seam: `container.after_seal` (serialised-containers.md §Plugin Interaction).
 */
final class ContainerSealed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public int $itemCount,
    ) {}
}
