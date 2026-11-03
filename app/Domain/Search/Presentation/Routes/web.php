<?php

declare(strict_types=1);

use App\Domain\Search\Presentation\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])
    ->get('search', SearchController::class)
    ->name('search');
