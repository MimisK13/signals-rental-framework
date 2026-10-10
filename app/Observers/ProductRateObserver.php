<?php

namespace App\Observers;

use App\Models\ProductRate;
use App\Services\RateEngine\RateResolver;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

final class ProductRateObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(ProductRate $rate): void
    {
        RateResolver::flushProduct((int) $rate->product_id);
    }

    public function deleted(ProductRate $rate): void
    {
        RateResolver::flushProduct((int) $rate->product_id);
    }
}
