<?php

declare(strict_types=1);

namespace App\Domain\Rates\Observers;

use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Services\RateEngine\RateResolver;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

final class ProductRateObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(ProductRate $rate): void
    {
        RateResolver::flushProduct((int) $rate->product_id);
        app(RateResolver::class)->flushRequestCache();
    }

    public function deleted(ProductRate $rate): void
    {
        RateResolver::flushProduct((int) $rate->product_id);
        app(RateResolver::class)->flushRequestCache();
    }
}
