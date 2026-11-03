<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Domain\Dashboard\Presentation\Livewire\GettingStartedChecklist;
use App\Domain\Dashboard\Presentation\Livewire\KpiCards;
use App\Domain\Dashboard\Presentation\Livewire\OpportunityPipeline;
use App\Domain\Dashboard\Presentation\Livewire\RecentOpportunities;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class DashboardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'dashboard');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');

        Livewire::addComponent(name: 'dashboard.kpi-cards', class: KpiCards::class);
        Livewire::addComponent(name: 'dashboard.opportunity-pipeline', class: OpportunityPipeline::class);
        Livewire::addComponent(name: 'dashboard.recent-opportunities', class: RecentOpportunities::class);
        Livewire::addComponent(name: 'dashboard.getting-started-checklist', class: GettingStartedChecklist::class);
    }
}
