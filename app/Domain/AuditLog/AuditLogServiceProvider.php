<?php

declare(strict_types=1);

namespace App\Domain\AuditLog;

use App\Domain\AuditLog\Console\PruneActionLogsCommand;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\AuditLog\Listeners\LogAction;
use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\AuditLog\Policies\ActionLogPolicy;
use App\Domain\AuditLog\Presentation\Livewire\Admin\Index;
use App\Domain\AuditLog\Settings\ActionLogSettings;
use App\Domain\Settings\Services\SettingsRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AuditLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(SettingsRegistry::class, function (SettingsRegistry $registry): void {
            $registry->register(new ActionLogSettings);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'audit-log');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Gate::policy(ActionLog::class, ActionLogPolicy::class);
        Event::listen(AuditableEvent::class, LogAction::class);
        $this->commands([PruneActionLogsCommand::class]);
        Livewire::addComponent(name: 'admin.settings.action-log', class: Index::class);
    }
}
