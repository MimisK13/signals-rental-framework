<?php

declare(strict_types=1);

namespace App\Domain\Documentation;

use App\Domain\Documentation\Services\DocsService;
use Illuminate\Support\ServiceProvider;

final class DocumentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DocsService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'documentation');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
    }
}
