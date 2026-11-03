<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Notifications\Database\Seeders\NotificationTypeSeeder;
use App\Domain\Notifications\Listeners\NotificationDispatchListener;
use App\Domain\Notifications\Presentation\Livewire\Admin\NotificationSettings;
use App\Domain\Notifications\Presentation\Livewire\Bell;
use App\Domain\Notifications\Presentation\Livewire\Pane;
use App\Domain\Notifications\Presentation\Livewire\Settings\Preferences;
use App\Domain\Notifications\Services\EventNotificationConditionEvaluator;
use App\Domain\Notifications\Services\EventNotificationMapRegistry;
use App\Domain\Notifications\Services\NotificationChannelResolver;
use App\Domain\Notifications\Services\NotificationRegistry;
use App\Domain\Notifications\Services\RecipientResolverRegistry;
use App\Domain\Notifications\Services\RecipientResolvers\ContactRecipientResolver;
use App\Domain\Notifications\Services\RecipientResolvers\RoleRecipientResolver;
use App\Domain\Notifications\Services\RecipientResolvers\SpecificMemberRecipientResolver;
use App\Domain\Notifications\Services\RecipientResolvers\SubjectOwnerRecipientResolver;
use App\Domain\Notifications\Services\RecipientResolvers\TriggerActorRecipientResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationRegistry::class, function (): NotificationRegistry {
            $registry = new NotificationRegistry;
            $registry->registerMany(NotificationTypeSeeder::types());

            return $registry;
        });

        $this->app->singleton(EventNotificationMapRegistry::class);
        $this->app->singleton(EventNotificationConditionEvaluator::class);
        $this->app->singleton(NotificationChannelResolver::class);
        $this->app->singleton(RecipientResolverRegistry::class, function (): RecipientResolverRegistry {
            $registry = new RecipientResolverRegistry;
            $registry->register(new SubjectOwnerRecipientResolver);
            $registry->register(new RoleRecipientResolver);
            $registry->register(new SpecificMemberRecipientResolver);
            $registry->register(new TriggerActorRecipientResolver);
            $registry->register(new ContactRecipientResolver);

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'notifications');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        foreach ([
            'settings.notifications' => Preferences::class,
            'admin.settings.notifications' => NotificationSettings::class,
            'notifications.bell' => Bell::class,
            'notifications.pane' => Pane::class,
        ] as $name => $class) {
            Livewire::addComponent(name: $name, class: $class);
        }

        Event::listen(AuditableEvent::class, NotificationDispatchListener::class);
    }
}
