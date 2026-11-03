<?php

declare(strict_types=1);

use App\Domain\Projects\Planning\Presentation\Livewire\Index as PlannerIndex;
use App\Domain\Projects\Presentation\Livewire\Index;
use App\Domain\Projects\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('projects', Index::class)->name('projects.index');
    Route::livewire('planner', PlannerIndex::class)->name('planner');
    Route::get('projects/create', fn () => redirect()->route('projects.index', ['panel' => 'create']))
        ->name('projects.create');
    Route::livewire('projects/{project}', Show::class)->name('projects.show');
    Route::get('projects/{project}/edit', fn (string $project) => redirect()->route('projects.index', [
        'panel' => 'edit',
        'project' => $project,
    ]))->name('projects.edit');
});
