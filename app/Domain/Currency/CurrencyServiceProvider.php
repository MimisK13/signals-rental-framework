<?php

declare(strict_types=1);

namespace App\Domain\Currency;

use App\Domain\Currency\Models\ExchangeRate;
use App\Domain\Currency\Policies\ExchangeRatePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class CurrencyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ExchangeRate::class, ExchangeRatePolicy::class);

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
    }
}
