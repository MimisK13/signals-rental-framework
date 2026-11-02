<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Events;

use App\Domain\Stock\Models\StockLevel;
use App\Models\Opportunity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

/**
 * Plugin seam: `container.before_repack` (serialised-containers.md §Plugin Interaction).
 *
 * Listeners may veto repack-on-return initiation by throwing {@see ValidationException}.
 */
final class BeforeContainerRepack
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Opportunity $opportunity,
        public StockLevel $housing,
    ) {}
}
