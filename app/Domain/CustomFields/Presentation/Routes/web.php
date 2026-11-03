<?php

declare(strict_types=1);

use App\Domain\CustomFields\Presentation\Livewire\Form;
use App\Domain\CustomFields\Presentation\Livewire\GroupForm;
use App\Domain\CustomFields\Presentation\Livewire\GroupsIndex;
use App\Domain\CustomFields\Presentation\Livewire\Index;
use App\Domain\CustomFields\Presentation\Livewire\ProductCustomFields;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/custom-fields', Index::class)->name('admin.settings.custom-fields');
    Route::livewire('admin/settings/custom-fields/create', Form::class)->name('admin.settings.custom-fields.create');
    Route::livewire('admin/settings/custom-fields/{customField}/edit', Form::class)->name('admin.settings.custom-fields.edit');
    Route::livewire('admin/settings/custom-field-groups', GroupsIndex::class)->name('admin.settings.custom-field-groups');
    Route::livewire('admin/settings/custom-field-groups/create', GroupForm::class)->name('admin.settings.custom-field-groups.create');
    Route::livewire('admin/settings/custom-field-groups/{customFieldGroup}/edit', GroupForm::class)->name('admin.settings.custom-field-groups.edit');
});

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('products/{product}/custom-fields', ProductCustomFields::class)->name('products.custom-fields');
});
