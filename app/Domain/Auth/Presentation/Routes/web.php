<?php

declare(strict_types=1);

use App\Domain\Auth\Presentation\Actions\Logout;
use App\Domain\Auth\Presentation\Http\Controllers\MagicLinkController;
use App\Domain\Auth\Presentation\Http\Controllers\SsoController;
use App\Domain\Auth\Presentation\Http\Controllers\VerifyEmailController;
use App\Domain\Auth\Presentation\Livewire\AcceptInvitation;
use App\Domain\Auth\Presentation\Livewire\ConfirmPassword;
use App\Domain\Auth\Presentation\Livewire\ForgotPassword;
use App\Domain\Auth\Presentation\Livewire\Login;
use App\Domain\Auth\Presentation\Livewire\ResetPassword;
use App\Domain\Auth\Presentation\Livewire\TwoFactorChallenge;
use App\Domain\Auth\Presentation\Livewire\VerifyEmail;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete'])->group(function (): void {
    Route::livewire('two-factor-challenge', TwoFactorChallenge::class)
        ->name('two-factor.challenge');

    Route::middleware('guest')->group(function (): void {
        Route::livewire('login', Login::class)->name('login');
        Route::livewire('forgot-password', ForgotPassword::class)->name('password.request');
        Route::livewire('reset-password/{token}', ResetPassword::class)->name('password.reset');

        Route::middleware('throttle:6,1')->group(function (): void {
            Route::get('auth/{provider}/redirect', [SsoController::class, 'redirect'])
                ->whereIn('provider', ['google', 'microsoft'])
                ->name('sso.redirect');

            Route::get('auth/{provider}/callback', [SsoController::class, 'callback'])
                ->whereIn('provider', ['google', 'microsoft'])
                ->name('sso.callback');

            Route::get('auth/magic-link/{token}', [MagicLinkController::class, 'consume'])
                ->name('magic-link.login');
        });
    });

    Route::middleware('auth')->group(function (): void {
        Route::livewire('verify-email', VerifyEmail::class)->name('verification.notice');
        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::livewire('confirm-password', ConfirmPassword::class)->name('password.confirm');
    });

    Route::livewire('invitation/{user}', AcceptInvitation::class)
        ->middleware('signed')
        ->name('invitation.accept');

    Route::post('logout', Logout::class)->name('logout');
});
