<?php

namespace App\Observers;

use App\Models\RateDefinition;
use App\Services\RateEngine\RateResolver;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

final class RateDefinitionObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(RateDefinition $definition): void
    {
        RateResolver::flushAll();
    }

    public function deleted(RateDefinition $definition): void
    {
        RateResolver::flushAll();
    }
}
