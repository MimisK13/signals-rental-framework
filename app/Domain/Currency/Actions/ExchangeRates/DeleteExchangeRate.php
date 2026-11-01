<?php

declare(strict_types=1);

namespace App\Domain\Currency\Actions\ExchangeRates;

use App\Domain\Currency\Models\ExchangeRate;
use Illuminate\Support\Facades\Gate;

final class DeleteExchangeRate
{
    public function __invoke(ExchangeRate $exchangeRate): void
    {
        Gate::authorize('settings.manage');

        $exchangeRate->delete();
    }
}
