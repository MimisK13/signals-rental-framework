<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Events;

use App\Domain\Warehouse\Containers\Models\Container;
use App\Models\Opportunity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Plugin seam: `container.before_dispatch` (serialised-containers.md §Plugin Interaction).
 *
 * Listeners may veto dispatch by throwing {@see ValidationException}.
 */
final class BeforeContainerDispatched
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public Opportunity $opportunity,
    ) {}
}
