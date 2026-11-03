<?php

declare(strict_types=1);

namespace App\Domain\Activities;

use App\Domain\Activities\Database\Seeders\ActivitySeeder;
use App\Domain\Activities\Enums\ActivityPriority;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Enums\ActivityType;
use App\Domain\Activities\Enums\TimeStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityParticipant;
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
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ActivitiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ActivityTypeList::class);

        // Historical migrations and queued subjects still contain these class names.
        foreach ([
            'App\\Models\\Activity' => Activity::class,
            'App\\Models\\ActivityParticipant' => ActivityParticipant::class,
            'App\\Enums\\ActivityPriority' => ActivityPriority::class,
            'App\\Enums\\ActivityStatus' => ActivityStatus::class,
            'App\\Enums\\ActivityType' => ActivityType::class,
            'App\\Enums\\TimeStatus' => TimeStatus::class,
            'Database\\Seeders\\ActivitySeeder' => ActivitySeeder::class,
        ] as $legacy => $canonical) {
            if (! class_exists($legacy, false)) {
                class_alias($canonical, $legacy);
            }
        }
    }

    public function boot(): void
    {
        // Preserve stored morph keys so existing audit, attachment and EAV rows stay visible.
        Relation::morphMap([
            'App\\Models\\Activity' => Activity::class,
            'App\\Models\\ActivityParticipant' => ActivityParticipant::class,
        ]);

        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy('App\\Models\\Activity', ActivityPolicy::class);

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
