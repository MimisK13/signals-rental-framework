<?php

declare(strict_types=1);

namespace App\Domain\Administration;

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Presentation\Livewire\Permissions;
use App\Domain\Administration\Presentation\Livewire\RoleForm;
use App\Domain\Administration\Presentation\Livewire\Roles;
use App\Domain\Administration\Presentation\Livewire\Settings\Appearance;
use App\Domain\Administration\Presentation\Livewire\Settings\DeleteAccount;
use App\Domain\Administration\Presentation\Livewire\Settings\Profile;
use App\Domain\Administration\Presentation\Livewire\UserForm;
use App\Domain\Administration\Presentation\Livewire\Users;
use App\Domain\Administration\Services\PermissionRegistry;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AdministrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionRegistry::class, function (): PermissionRegistry {
            $registry = new PermissionRegistry;
            $registry->registerMany(PermissionSeeder::permissions());

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'administration');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Livewire::addComponent(name: 'admin.settings.users', class: Users::class);
        Livewire::addComponent(name: 'admin.settings.user-form', class: UserForm::class);
        Livewire::addComponent(name: 'admin.settings.roles', class: Roles::class);
        Livewire::addComponent(name: 'admin.settings.role-form', class: RoleForm::class);
        Livewire::addComponent(name: 'admin.settings.permissions', class: Permissions::class);
        Livewire::addComponent(name: 'settings.profile', class: Profile::class);
        Livewire::addComponent(name: 'settings.delete-account', class: DeleteAccount::class);
        Livewire::addComponent(name: 'settings.appearance', class: Appearance::class);
    }
}
