<?php

namespace App\Events\Containers;

use App\Models\Container;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Plugin seam: `container.before_seal` (serialised-containers.md §Plugin Interaction).
 *
 * Listeners may veto the seal by throwing {@see ValidationException}.
 */
class BeforeContainerSealed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
    ) {}
}
