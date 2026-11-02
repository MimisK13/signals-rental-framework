<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

use App\Domain\Documents\Services\DocumentResolverRegistry;
use App\Domain\Documents\Services\DocumentTypeRegistry;
use App\Domain\Quotations\Events\QuotationDomainEvent;
use App\Domain\Quotations\Listeners\DispatchQuotationWebhook;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Policies\QuotationPolicy;
use App\Domain\Quotations\Presentation\Livewire\Editor;
use App\Domain\Quotations\Presentation\Livewire\Form;
use App\Domain\Quotations\Presentation\Livewire\Index;
use App\Services\Api\AbilityRegistry;
use App\Services\Api\WebhookEventRegistry;
use App\Services\NotificationRegistry;
use App\Services\Notifications\EventNotificationMapRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class QuotationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(AbilityRegistry::class, function (AbilityRegistry $registry): void {
            $registry->register('quotations:read', ['label' => 'Read quotations', 'group' => 'Quotations']);
            $registry->register('quotations:write', ['label' => 'Manage quotations', 'group' => 'Quotations']);
        });
        $this->app->afterResolving(DocumentResolverRegistry::class, function (DocumentResolverRegistry $registry): void {
            $registry->register($this->app->make(Services\QuotationDataResolver::class));
        });
        $this->app->afterResolving(DocumentTypeRegistry::class, function (DocumentTypeRegistry $registry): void {
            $registry->register(
                documentType: 'quotation',
                resolverType: 'quotation',
                modelType: Quotation::class,
                label: 'Quotation',
                description: 'Renders an independent or Project-first quotation.',
            );
        });
        $this->app->afterResolving(WebhookEventRegistry::class, function (WebhookEventRegistry $registry): void {
            foreach (Services\QuotationEventDefinitions::all() as $name) {
                $registry->register($name, ['group' => 'Quotations']);
            }
        });
        $this->app->afterResolving(EventNotificationMapRegistry::class, function (EventNotificationMapRegistry $registry): void {
            foreach (Services\QuotationEventDefinitions::all() as $name) {
                $registry->register($name, [['type' => $name]]);
            }
        });
        $this->app->afterResolving(NotificationRegistry::class, function (NotificationRegistry $registry): void {
            $registry->registerMany(Services\QuotationEventDefinitions::notifications());
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        Gate::policy(Quotation::class, QuotationPolicy::class);
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'quotations');
        Livewire::addComponent(name: 'quotations.index', class: Index::class);
        Livewire::addComponent(name: 'quotations.form', class: Form::class);
        Livewire::addComponent(name: 'quotations.editor', class: Editor::class);
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
        Event::listen(QuotationDomainEvent::class, DispatchQuotationWebhook::class);
    }
}
