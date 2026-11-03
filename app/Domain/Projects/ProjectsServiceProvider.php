<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Opportunities\Guards\Contracts\ApprovalGate;
use App\Domain\Projects\Opportunities\Guards\PluginValidatorRegistry;
use App\Domain\Projects\Opportunities\Guards\Rules\DispatchShortageRule;
use App\Domain\Projects\Opportunities\Guards\Rules\FxTaxLockRule;
use App\Domain\Projects\Opportunities\Guards\Rules\ShortageConfirmationRule;
use App\Domain\Projects\Opportunities\Guards\Stages\AutoApprovalGate;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityCost;
use App\Domain\Projects\Opportunities\Policies\OpportunityCostPolicy;
use App\Domain\Projects\Opportunities\Policies\OpportunityPolicy;
use App\Domain\Projects\Opportunities\Presentation\Columns\OpportunityColumnRegistry;
use App\Domain\Projects\Opportunities\Presentation\Columns\OpportunityCostColumnRegistry;
use App\Domain\Projects\Opportunities\Presentation\Columns\OpportunityItemColumnRegistry;
use App\Domain\Projects\Opportunities\Presentation\Columns\OpportunityVersionColumnRegistry;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Assets;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Costs;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Form;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Index as OpportunitiesIndex;
use App\Domain\Projects\Opportunities\Presentation\Livewire\LineItems;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Participants;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Show as OpportunityShow;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Versions;
use App\Domain\Projects\Opportunities\Services\AutoPromotionContext;
use App\Domain\Projects\Opportunities\Services\Hooks\ApprovalChainRegistry;
use App\Domain\Projects\Opportunities\Services\Hooks\WorkflowTriggerRegistry;
use App\Domain\Projects\Opportunities\Services\TransitionRuleRegistry;
use App\Domain\Projects\Opportunities\Settings\OpportunitySettings;
use App\Domain\Projects\Policies\ProjectPolicy;
use App\Domain\Projects\Presentation\Livewire\Index;
use App\Domain\Projects\Presentation\Livewire\Show;
use App\Domain\Settings\Services\SettingsRegistry;
use App\Services\ColumnRegistryResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ProjectsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AutoPromotionContext::class);
        $this->app->singleton(AutoApprovalGate::class);
        $this->app->bind(ApprovalGate::class, AutoApprovalGate::class);
        $this->app->singleton(PluginValidatorRegistry::class);
        $this->app->singleton(ApprovalChainRegistry::class);
        $this->app->singleton(WorkflowTriggerRegistry::class);

        $this->app->singleton(TransitionRuleRegistry::class, function ($app): TransitionRuleRegistry {
            $registry = new TransitionRuleRegistry;
            $registry->register($app->make(ShortageConfirmationRule::class));
            $registry->register($app->make(FxTaxLockRule::class));
            $registry->register($app->make(DispatchShortageRule::class));

            return $registry;
        });

        $this->app->afterResolving(SettingsRegistry::class, function (SettingsRegistry $registry): void {
            $registry->register(new OpportunitySettings);
        });
        $this->app->afterResolving(ColumnRegistryResolver::class, function (ColumnRegistryResolver $resolver): void {
            $resolver->register('opportunities', OpportunityColumnRegistry::class);
            $resolver->register('opportunity_items', OpportunityItemColumnRegistry::class);
            $resolver->register('opportunity_costs', OpportunityCostColumnRegistry::class);
            $resolver->register('opportunity_versions', OpportunityVersionColumnRegistry::class);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadMigrationsFrom(__DIR__.'/Opportunities/Database/Migrations');
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(OpportunityCost::class, OpportunityCostPolicy::class);

        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'projects');
        $this->loadViewsFrom(__DIR__.'/Opportunities/Presentation/Views', 'project-opportunities');
        Livewire::addComponent(name: 'projects.index', class: Index::class);
        Livewire::addComponent(name: 'projects.show', class: Show::class);
        Livewire::addComponent(name: 'opportunities.index', class: OpportunitiesIndex::class);
        Livewire::addComponent(name: 'opportunities.form', class: Form::class);
        Livewire::addComponent(name: 'opportunities.show', class: OpportunityShow::class);
        Livewire::addComponent(name: 'opportunities.assets', class: Assets::class);
        Livewire::addComponent(name: 'opportunities.costs', class: Costs::class);
        Livewire::addComponent(name: 'opportunities.participants', class: Participants::class);
        Livewire::addComponent(name: 'opportunities.versions', class: Versions::class);
        Livewire::addComponent(name: 'opportunities.line-items', class: LineItems::class);

        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/Opportunities/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Opportunities/Presentation/Routes/api.php');
    }
}
