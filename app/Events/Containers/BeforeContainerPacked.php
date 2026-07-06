<?php

namespace App\Events\Containers;

use App\Models\Container;
use App\Models\StockLevel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Plugin seam: `container.before_pack` (serialised-containers.md §Plugin Interaction).
 *
 * Listeners may veto the pack by throwing {@see ValidationException}.
 */
class BeforeContainerPacked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public StockLevel $stockLevel,
    ) {}
}
