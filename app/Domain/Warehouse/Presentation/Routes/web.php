<?php

declare(strict_types=1);

use App\Domain\Warehouse\Containers\Presentation\Livewire\CreateContainer;
use App\Domain\Warehouse\Containers\Presentation\Livewire\Index;
use App\Domain\Warehouse\Containers\Presentation\Livewire\Pack;
use App\Domain\Warehouse\Containers\Presentation\Livewire\Repack;
use App\Domain\Warehouse\Containers\Presentation\Livewire\Show;
use App\Domain\Warehouse\Presentation\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('warehouse', Dashboard::class)->name('warehouse.index');
    Route::livewire('containers', Index::class)->name('containers.index');
    Route::livewire('containers/create', CreateContainer::class)->name('containers.create');
    Route::livewire('containers/{container}', Show::class)->name('containers.show');
    Route::livewire('containers/{container}/pack', Pack::class)->name('containers.pack');
    Route::livewire('containers/{container}/repack/{opportunity}', Repack::class)->name('containers.repack');
});
