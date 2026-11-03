<?php

declare(strict_types=1);

namespace App\Domain\CustomFields;

use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use App\Domain\CustomFields\Policies\CustomFieldGroupPolicy;
use App\Domain\CustomFields\Policies\CustomFieldPolicy;
use App\Domain\CustomFields\Presentation\Livewire\Form;
use App\Domain\CustomFields\Presentation\Livewire\GroupForm;
use App\Domain\CustomFields\Presentation\Livewire\GroupsIndex;
use App\Domain\CustomFields\Presentation\Livewire\Index;
use App\Domain\CustomFields\Presentation\Livewire\OpportunityCustomFields;
use App\Domain\CustomFields\Presentation\Livewire\ProductCustomFields;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class CustomFieldsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'custom-fields');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
        Gate::policy(CustomField::class, CustomFieldPolicy::class);
        Gate::policy(CustomFieldGroup::class, CustomFieldGroupPolicy::class);
        Livewire::addComponent(name: 'admin.settings.custom-fields', class: Index::class);
        Livewire::addComponent(name: 'admin.settings.custom-field-form', class: Form::class);
        Livewire::addComponent(name: 'admin.settings.custom-field-groups', class: GroupsIndex::class);
        Livewire::addComponent(name: 'admin.settings.custom-field-group-form', class: GroupForm::class);
        Livewire::addComponent(name: 'products.custom-fields', class: ProductCustomFields::class);
        Livewire::addComponent(name: 'opportunities.custom-fields', class: OpportunityCustomFields::class);
    }
}
