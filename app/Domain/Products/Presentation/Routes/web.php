<?php

declare(strict_types=1);

use App\Domain\Products\Presentation\Livewire\Accessories;
use App\Domain\Products\Presentation\Livewire\ContainerConfig;
use App\Domain\Products\Presentation\Livewire\Form;
use App\Domain\Products\Presentation\Livewire\Index;
use App\Domain\Products\Presentation\Livewire\Investment;
use App\Domain\Products\Presentation\Livewire\ProductGroups\Index as ProductGroupsIndex;
use App\Domain\Products\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('products', Index::class)->name('products.index');
    Route::livewire('products/create', Form::class)->name('products.create');
    Route::livewire('products/{product}', Show::class)->name('products.show');
    Route::livewire('products/{product}/investment', Investment::class)->name('products.investment');
    Route::livewire('products/{product}/edit', Form::class)->name('products.edit');
    Route::livewire('products/{product}/accessories', Accessories::class)->name('products.accessories');
    Route::livewire('products/{product}/container-config', ContainerConfig::class)->name('products.container-config');

    Route::livewire('product-groups', ProductGroupsIndex::class)->name('product-groups.index');
});
