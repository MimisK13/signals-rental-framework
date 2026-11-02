<?php

declare(strict_types=1);

use App\Domain\Lists\Presentation\Livewire\ListNameForm;
use App\Domain\Lists\Presentation\Livewire\ListNames;
use App\Domain\Lists\Presentation\Livewire\ListValueForm;
use App\Domain\Lists\Presentation\Livewire\ListValues;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/list-names', ListNames::class)->name('admin.settings.list-names');
    Route::livewire('admin/settings/list-names/create', ListNameForm::class)->name('admin.settings.list-names.create');
    Route::livewire('admin/settings/list-names/{listName}/edit', ListNameForm::class)->name('admin.settings.list-names.edit');
    Route::livewire('admin/settings/lists/{listName}', ListValues::class)->name('admin.settings.lists');
    Route::livewire('admin/settings/lists/{listName}/create', ListValueForm::class)->name('admin.settings.list-values.create');
    Route::livewire('admin/settings/lists/{listName}/{listValue}/edit', ListValueForm::class)->name('admin.settings.list-values.edit');
});
