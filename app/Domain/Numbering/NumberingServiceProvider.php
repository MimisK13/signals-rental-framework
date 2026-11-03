<?php

declare(strict_types=1);

namespace App\Domain\Numbering;

use App\Domain\Numbering\Models\DocumentSequence;
use App\Domain\Numbering\Policies\DocumentSequencePolicy;
use App\Domain\Numbering\Policies\NumberingPolicy;
use App\Domain\Numbering\Presentation\Livewire\Admin\Numbering;
use App\Domain\Numbering\Support\NumberingAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class NumberingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'numbering');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Gate::policy(DocumentSequence::class, DocumentSequencePolicy::class);
        Gate::policy(NumberingAccess::class, NumberingPolicy::class);

        Livewire::addComponent(name: 'admin.settings.numbering', class: Numbering::class);
    }
}
