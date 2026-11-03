<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('dashboard', 'dashboard::index')
    ->middleware(['web', 'signals.setup-complete', 'auth', 'verified', '2fa', 'signals.session-timeout'])
    ->name('dashboard');
