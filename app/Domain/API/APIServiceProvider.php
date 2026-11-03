<?php

declare(strict_types=1);

namespace App\Domain\API;

use App\Domain\API\Console\ApiFlushLogs;
use App\Domain\API\Console\ApiPruneLogs;
use App\Domain\API\Presentation\Livewire\Explorer;
use App\Domain\API\Presentation\Livewire\Index;
use App\Domain\API\Presentation\Livewire\Logs;
use App\Domain\API\Presentation\Livewire\TokenManagement;
use App\Domain\API\Presentation\Livewire\Usage;
use App\Domain\API\Services\AbilityRegistry;
use App\Domain\API\Services\ApiEndpointCatalogue;
use App\Domain\API\Services\CoreAbilityDefinitions;
use App\Domain\API\Services\PriceWeightResolver;
use App\Domain\API\Services\UsageRecorder;
use App\Domain\API\Settings\ApiSettings;
use App\Services\SettingsRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

final class APIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AbilityRegistry::class, function (): AbilityRegistry {
            $registry = new AbilityRegistry;
            $registry->registerMany(CoreAbilityDefinitions::all());

            return $registry;
        });

        $this->app->singleton(PriceWeightResolver::class);
        $this->app->singleton(ApiEndpointCatalogue::class);
        $this->app->singleton(UsageRecorder::class);

        $this->app->afterResolving(SettingsRegistry::class, function (SettingsRegistry $registry): void {
            $registry->register(new ApiSettings);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'api');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Blade::anonymousComponentPath(__DIR__.'/Presentation/Views/components', 'api');

        Livewire::addComponent(name: 'api.index', class: Index::class);
        Livewire::addComponent(name: 'api.explorer', class: Explorer::class);
        Livewire::addComponent(name: 'api.logs', class: Logs::class);
        Livewire::addComponent(name: 'api.usage', class: Usage::class);
        Livewire::addComponent(name: 'admin.settings.api', class: TokenManagement::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ApiFlushLogs::class, ApiPruneLogs::class]);
        }

        RateLimiter::for('api', function ($request): Limit {
            try {
                $limit = max((int) settings('api.rate_limit', 60), 1);
                $unauthenticatedLimit = max((int) settings('api.rate_limit_unauthenticated', 20), 1);
            } catch (\Throwable) {
                $limit = 60;
                $unauthenticatedLimit = 20;
            }

            if (! $request->user()) {
                return Limit::perMinute($unauthenticatedLimit)->by($request->ip());
            }

            $token = $request->user()->currentAccessToken();

            if ($token instanceof PersonalAccessToken && $token->rate_limit_per_minute) {
                return Limit::perMinute($token->rate_limit_per_minute)->by('token:'.$token->id);
            }

            return Limit::perMinute($limit)->by((string) $request->user()->getAuthIdentifier());
        });

        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            if ($event->command === 'route:clear') {
                app(ApiEndpointCatalogue::class)->invalidateAll();
            }
        });
    }
}
