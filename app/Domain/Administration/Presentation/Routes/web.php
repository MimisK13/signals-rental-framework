<?php

declare(strict_types=1);

use App\Domain\Administration\Presentation\Livewire\Permissions;
use App\Domain\Administration\Presentation\Livewire\RoleForm;
use App\Domain\Administration\Presentation\Livewire\Roles;
use App\Domain\Administration\Presentation\Livewire\UserForm;
use App\Domain\Administration\Presentation\Livewire\Users;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/users', Users::class)->name('admin.settings.users');
    Route::livewire('admin/settings/users/{user}/edit', UserForm::class)->name('admin.settings.users.edit');
    Route::livewire('admin/settings/roles', Roles::class)->name('admin.settings.roles');
    Route::livewire('admin/settings/roles/create', RoleForm::class)->name('admin.settings.roles.create');
    Route::livewire('admin/settings/roles/{role}/edit', RoleForm::class)->name('admin.settings.roles.edit');
    Route::livewire('admin/settings/permissions', Permissions::class)->name('admin.settings.permissions');
});
