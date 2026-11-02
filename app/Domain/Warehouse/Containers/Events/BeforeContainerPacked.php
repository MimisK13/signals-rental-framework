<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Events;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Models\Container;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Plugin seam: `container.before_pack` (serialised-containers.md §Plugin Interaction).
 *
 * Listeners may veto the pack by throwing {@see ValidationException}.
 */
final class BeforeContainerPacked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public StockLevel $stockLevel,
    ) {}
}
