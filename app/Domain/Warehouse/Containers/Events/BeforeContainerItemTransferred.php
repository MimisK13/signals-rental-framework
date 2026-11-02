<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Events;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Models\ContainerItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Plugin seam: `container.before_transfer` (serialised-containers.md §Plugin Interaction).
 *
 * Listeners may veto the transfer by throwing {@see ValidationException}.
 */
final class BeforeContainerItemTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $sourceContainer,
        public Container $targetContainer,
        public StockLevel $stockLevel,
        public ContainerItem $containerItem,
    ) {}
}
