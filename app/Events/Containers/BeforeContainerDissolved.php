<?php

namespace App\Events\Containers;

use App\Models\Container;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Plugin seam: `container.before_dissolve` (serialised-containers.md §Plugin Interaction).
 *
 * Listeners may veto dissolution by throwing {@see ValidationException}.
 */
class BeforeContainerDissolved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
    ) {}
}
