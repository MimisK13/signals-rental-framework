<?php

namespace App\Events\Containers;

use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\StockLevel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Plugin seam: `container.before_transfer` (serialised-containers.md §Plugin Interaction).
 *
 * Listeners may veto the transfer by throwing {@see ValidationException}.
 */
class BeforeContainerItemTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $sourceContainer,
        public Container $targetContainer,
        public StockLevel $stockLevel,
        public ContainerItem $containerItem,
    ) {}
}
