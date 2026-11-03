<?php

declare(strict_types=1);

namespace App\Domain\Webhooks;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Webhooks\Listeners\DispatchWebhookForAuditableEvent;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Policies\WebhookPolicy;
use App\Domain\Webhooks\Presentation\Livewire\Index;
use App\Domain\Webhooks\Services\WebhookEventRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class WebhooksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WebhookEventRegistry::class, function (): WebhookEventRegistry {
            $registry = new WebhookEventRegistry;

            foreach (WebhookEventRegistry::CORE_EVENT_NAMES as $name) {
                $registry->register($name);
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'webhooks');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Gate::policy(Webhook::class, WebhookPolicy::class);
        Event::listen(AuditableEvent::class, DispatchWebhookForAuditableEvent::class);
        Livewire::addComponent(name: 'api.webhooks', class: Index::class);
    }
}
