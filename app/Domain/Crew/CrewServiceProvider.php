<?php

declare(strict_types=1);

namespace App\Domain\Crew;

use App\Domain\API\Services\AbilityRegistry;
use App\Domain\Crew\Console\AnonymiseExpiredCrewContacts;
use App\Domain\Crew\Console\ExpireCrewInvitations;
use App\Domain\Crew\Console\ScanCrewQualifications;
use App\Domain\Crew\Console\ScanCrewShortages;
use App\Domain\Crew\Events\CrewDomainEvent;
use App\Domain\Crew\Listeners\DispatchCrewWebhook;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Observers\CrewProjectScheduleObserver;
use App\Domain\Crew\Policies\CrewAssignmentPolicy;
use App\Domain\Crew\Policies\CrewAvailabilityPolicy;
use App\Domain\Crew\Policies\CrewExpensePolicy;
use App\Domain\Crew\Policies\CrewMemberPolicy;
use App\Domain\Crew\Policies\CrewRateCardPolicy;
use App\Domain\Crew\Policies\CrewRequestPolicy;
use App\Domain\Crew\Policies\CrewShiftPolicy;
use App\Domain\Crew\Policies\CrewTimesheetPolicy;
use App\Domain\Crew\Presentation\Livewire\Calendar;
use App\Domain\Crew\Presentation\Livewire\Configuration;
use App\Domain\Crew\Presentation\Livewire\Dashboard;
use App\Domain\Crew\Presentation\Livewire\Members\Form;
use App\Domain\Crew\Presentation\Livewire\Members\Index;
use App\Domain\Crew\Presentation\Livewire\Members\Show;
use App\Domain\Crew\Presentation\Livewire\Planner;
use App\Domain\Crew\Presentation\Livewire\Rates;
use App\Domain\Crew\Services\CrewCoordinatorRecipientResolver;
use App\Domain\Crew\Services\CrewEventDefinitions;
use App\Domain\Crew\Services\CrewRecipientResolver;
use App\Domain\Crew\Services\CrewStatusRegistry;
use App\Domain\Crew\Settings\CrewSettings;
use App\Domain\Notifications\Services\EventNotificationMapRegistry;
use App\Domain\Notifications\Services\RecipientResolverRegistry;
use App\Domain\Projects\Models\Project;
use App\Domain\Settings\Services\SettingsRegistry;
use App\Domain\Webhooks\Services\WebhookEventRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Wires the Crew domain into the application: schema, factories, settings,
 * lifecycle registry, and authorization. Nothing Crew-related lives outside
 * this domain except the permission catalogue and the provider registration.
 */
final class CrewServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private const POLICIES = [
        CrewMember::class => CrewMemberPolicy::class,
        CrewRateCard::class => CrewRateCardPolicy::class,
        CrewRequest::class => CrewRequestPolicy::class,
        CrewAssignment::class => CrewAssignmentPolicy::class,
        CrewShift::class => CrewShiftPolicy::class,
        CrewAvailability::class => CrewAvailabilityPolicy::class,
        CrewTimesheet::class => CrewTimesheetPolicy::class,
        CrewExpense::class => CrewExpensePolicy::class,
    ];

    public function register(): void
    {
        $this->app->scoped(CrewStatusRegistry::class);
        $this->app->afterResolving(AbilityRegistry::class, function (AbilityRegistry $registry): void {
            $registry->register('crew:read', ['label' => 'Read crew', 'group' => 'Crew']);
            $registry->register('crew:write', ['label' => 'Manage crew', 'group' => 'Crew']);
        });
        $this->app->afterResolving(WebhookEventRegistry::class, function (WebhookEventRegistry $registry): void {
            foreach (CrewEventDefinitions::WEBHOOKS as $name) {
                $registry->register($name, ['group' => 'Crew']);
            }
        });
        $this->app->afterResolving(EventNotificationMapRegistry::class, function (EventNotificationMapRegistry $registry): void {
            foreach (CrewEventDefinitions::NOTIFICATIONS as $name) {
                $registry->register($name, [['type' => $name]]);
            }
        });
        $this->app->afterResolving(RecipientResolverRegistry::class, function (RecipientResolverRegistry $registry): void {
            $registry->register(new CrewRecipientResolver);
            $registry->register(new CrewCoordinatorRecipientResolver);
        });

        // The settings registry is a plain singleton assembled by
        // SettingsServiceProvider; extend it rather than editing that provider.
        $this->app->afterResolving(SettingsRegistry::class, function (SettingsRegistry $registry): void {
            $registry->register(new CrewSettings);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'crew');
        $this->registerPresentation();
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Event::listen(CrewDomainEvent::class, DispatchCrewWebhook::class);
        Project::observe(CrewProjectScheduleObserver::class);
        $this->commands([
            AnonymiseExpiredCrewContacts::class,
            ExpireCrewInvitations::class,
            ScanCrewShortages::class,
            ScanCrewQualifications::class,
        ]);
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('crew:anonymise-expired-contacts')->daily()->withoutOverlapping();
            $schedule->command('crew:expire-invitations')->hourly()->withoutOverlapping();
            $schedule->command('crew:scan-shortages')->daily()->withoutOverlapping();
            $schedule->command('crew:scan-qualifications')->daily()->withoutOverlapping();
        });
        $this->registerFactories();
        $this->registerPolicies();
    }

    private function registerPresentation(): void
    {
        $components = [
            'crew.dashboard' => Dashboard::class,
            'crew.calendar' => Calendar::class,
            'crew.planner' => Planner::class,
            'crew.configuration' => Configuration::class,
            'crew.rates' => Rates::class,
            'crew.members.index' => Index::class,
            'crew.members.form' => Form::class,
            'crew.members.show' => Show::class,
            'crew.requests.index' => Presentation\Livewire\Requests\Index::class,
            'crew.requests.show' => Presentation\Livewire\Requests\Show::class,
            'crew.assignments.show' => Presentation\Livewire\Assignments\Show::class,
            'crew.timesheets.index' => Presentation\Livewire\Timesheets\Index::class,
            'crew.timesheets.show' => Presentation\Livewire\Timesheets\Show::class,
        ];

        foreach ($components as $name => $class) {
            Livewire::addComponent(name: $name, class: $class);
        }
    }

    /**
     * Crew factories live beside the domain models instead of `database/factories`.
     */
    private function registerFactories(): void
    {
        Factory::guessFactoryNamesUsing(static function (string $modelName): string {
            if (str_starts_with($modelName, 'App\\Domain\\Crew\\Models\\')) {
                return 'App\\Domain\\Crew\\Database\\Factories\\'.class_basename($modelName).'Factory';
            }

            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });
    }

    private function registerPolicies(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
