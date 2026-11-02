<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Events;

use App\Domain\Warehouse\Containers\Enums\ContainerUnsealReason;
use App\Domain\Warehouse\Containers\Models\Container;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ContainerUnsealed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public ContainerUnsealReason $reason,
    ) {}
}
