<?php

namespace App\Events\Containers;

use App\Enums\ContainerUnsealReason;
use App\Models\Container;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContainerUnsealed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Container $container,
        public ContainerUnsealReason $reason,
    ) {}
}
