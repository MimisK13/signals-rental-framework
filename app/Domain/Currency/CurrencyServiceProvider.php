<?php

declare(strict_types=1);

namespace App\Domain\Currency;

use App\Domain\Currency\Database\Seeders\CurrencySeeder;
use App\Domain\Currency\Database\Seeders\ExchangeRateSeeder;
use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Models\ExchangeRate;
use App\Domain\Currency\Policies\ExchangeRatePolicy;
use App\Domain\Currency\Services\CurrencyService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class CurrencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Keep historical model references and existing service/seeder entry points valid.
        foreach ([
            'App\\Models\\Currency' => Currency::class,
            'App\\Models\\ExchangeRate' => ExchangeRate::class,
            'App\\Services\\CurrencyService' => CurrencyService::class,
            'Database\\Seeders\\CurrencySeeder' => CurrencySeeder::class,
            'Database\\Seeders\\ExchangeRateSeeder' => ExchangeRateSeeder::class,
        ] as $legacy => $canonical) {
            if (! class_exists($legacy, false)) {
                class_alias($canonical, $legacy);
            }
        }

        $this->app->alias(CurrencyService::class, 'App\\Services\\CurrencyService');
    }

    public function boot(): void
    {
        Relation::morphMap([
            'App\\Models\\Currency' => Currency::class,
            'App\\Models\\ExchangeRate' => ExchangeRate::class,
        ]);

        Gate::policy(ExchangeRate::class, ExchangeRatePolicy::class);
        Gate::policy('App\\Models\\ExchangeRate', ExchangeRatePolicy::class);

        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
    }
}
