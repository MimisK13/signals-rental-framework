<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Policies\ProjectPolicy;
use App\Domain\Projects\Presentation\Livewire\Index;
use App\Domain\Projects\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ProjectsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);

        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'projects');
        Livewire::addComponent(name: 'projects.index', class: Index::class);
        Livewire::addComponent(name: 'projects.show', class: Show::class);

        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
    }
}
