<?php

namespace App\Events\Containers;

use App\Models\Opportunity;
use App\Models\StockLevel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Plugin seam: `container.before_repack` (serialised-containers.md §Plugin Interaction).
 *
 * Listeners may veto repack-on-return initiation by throwing {@see ValidationException}.
 */
class BeforeContainerRepack
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Opportunity $opportunity,
        public StockLevel $housing,
    ) {}
}
