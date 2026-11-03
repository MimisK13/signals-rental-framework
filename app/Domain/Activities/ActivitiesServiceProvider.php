<?php

declare(strict_types=1);

namespace App\Domain\Activities;

use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Policies\ActivityPolicy;
use App\Domain\Activities\Presentation\Livewire\Calendar\ActivityDetailModal;
use App\Domain\Activities\Presentation\Livewire\Calendar\ActivityFormModal;
use App\Domain\Activities\Presentation\Livewire\Calendar\Index as CalendarIndex;
use App\Domain\Activities\Presentation\Livewire\Embedded\MemberActivities;
use App\Domain\Activities\Presentation\Livewire\Embedded\OpportunityActivities;
use App\Domain\Activities\Presentation\Livewire\Embedded\ProductActivities;
use App\Domain\Activities\Presentation\Livewire\Embedded\StockLevelActivities;
use App\Domain\Activities\Presentation\Livewire\Form;
use App\Domain\Activities\Presentation\Livewire\Index;
use App\Domain\Activities\Presentation\Livewire\Settings\Calendar as CalendarSettings;
use App\Domain\Activities\Presentation\Livewire\Show;
use App\Domain\Activities\Services\ActivityTypeList;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ActivitiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ActivityTypeList::class);
    }

    public function boot(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'activities');
        foreach ([
            'activities.index' => Index::class,
            'activities.form' => Form::class,
            'activities.show' => Show::class,
            'calendar.index' => CalendarIndex::class,
            'calendar.activity-form-modal' => ActivityFormModal::class,
            'calendar.activity-detail-modal' => ActivityDetailModal::class,
            'members.activities' => MemberActivities::class,
            'products.activities' => ProductActivities::class,
            'stock-levels.activities' => StockLevelActivities::class,
            'opportunities.activities' => OpportunityActivities::class,
            'settings.calendar' => CalendarSettings::class,
        ] as $name => $class) {
            Livewire::addComponent(name: $name, class: $class);
        }

        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
    }
}
