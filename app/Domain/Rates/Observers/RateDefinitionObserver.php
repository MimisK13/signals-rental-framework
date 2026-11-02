<?php

declare(strict_types=1);

namespace App\Domain\Rates\Observers;

use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Rates\Services\RateEngine\RateResolver;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

final class RateDefinitionObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(RateDefinition $definition): void
    {
        RateResolver::flushAll();
        app(RateResolver::class)->flushRequestCache();
    }

    public function deleted(RateDefinition $definition): void
    {
        RateResolver::flushAll();
        app(RateResolver::class)->flushRequestCache();
    }
}
