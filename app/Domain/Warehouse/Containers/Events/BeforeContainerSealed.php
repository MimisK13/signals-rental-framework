<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Events;

use App\Domain\Warehouse\Containers\Models\Container;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Plugin seam: `container.before_seal` (serialised-containers.md §Plugin Interaction).
 *
 * Listeners may veto the seal by throwing {@see ValidationException}.
 */
final class BeforeContainerSealed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
    ) {}
}
