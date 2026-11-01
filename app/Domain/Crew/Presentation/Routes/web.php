<?php

declare(strict_types=1);

use App\Domain\Crew\Presentation\Livewire\Calendar;
use App\Domain\Crew\Presentation\Livewire\Configuration;
use App\Domain\Crew\Presentation\Livewire\Dashboard;
use App\Domain\Crew\Presentation\Livewire\Members\Form;
use App\Domain\Crew\Presentation\Livewire\Members\Index;
use App\Domain\Crew\Presentation\Livewire\Members\Show;
use App\Domain\Crew\Presentation\Livewire\Planner;
use App\Domain\Crew\Presentation\Livewire\Rates;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout', 'module:crew', 'can:crew.access'])
    ->prefix('crew')->name('crew.')->group(function (): void {
        Route::livewire('/', Dashboard::class)->name('dashboard');
        Route::livewire('/members', Index::class)->name('members.index');
        Route::livewire('/members/create', Form::class)->name('members.create');
        Route::livewire('/members/{crewMember}/edit', Form::class)->name('members.edit');
        Route::livewire('/members/{crewMember}', Show::class)->name('members.show');
        Route::livewire('/requests', App\Domain\Crew\Presentation\Livewire\Requests\Index::class)->name('requests.index');
        Route::livewire('/requests/{crewRequest}', App\Domain\Crew\Presentation\Livewire\Requests\Show::class)->name('requests.show');
        Route::livewire('/planner', Planner::class)->name('planner');
        Route::livewire('/calendar', Calendar::class)->name('calendar');
        Route::livewire('/roles', Configuration::class)->defaults('kind', 'roles')->name('roles.index');
        Route::livewire('/skills', Configuration::class)->defaults('kind', 'skills')->name('skills.index');
        Route::livewire('/qualifications', Configuration::class)->defaults('kind', 'qualifications')->name('qualifications.index');
        Route::livewire('/rates', Rates::class)->name('rates.index');
        Route::livewire('/assignments/{crewAssignment}', App\Domain\Crew\Presentation\Livewire\Assignments\Show::class)->name('assignments.show');
        Route::livewire('/timesheets', App\Domain\Crew\Presentation\Livewire\Timesheets\Index::class)->name('timesheets.index');
        Route::livewire('/timesheets/{crewTimesheet}', App\Domain\Crew\Presentation\Livewire\Timesheets\Show::class)->name('timesheets.show');
    });
