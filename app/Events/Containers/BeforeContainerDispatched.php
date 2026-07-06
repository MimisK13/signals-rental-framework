<?php

namespace App\Events\Containers;

use App\Models\Container;
use App\Models\Opportunity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Plugin seam: `container.before_dispatch` (serialised-containers.md §Plugin Interaction).
 *
 * Listeners may veto dispatch by throwing {@see ValidationException}.
 */
class BeforeContainerDispatched
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public Opportunity $opportunity,
    ) {}
}
