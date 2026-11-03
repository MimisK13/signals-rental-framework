<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Auth\Presentation\Livewire\AcceptInvitation;
use App\Domain\Auth\Presentation\Livewire\ConfirmPassword;
use App\Domain\Auth\Presentation\Livewire\ForgotPassword;
use App\Domain\Auth\Presentation\Livewire\Login;
use App\Domain\Auth\Presentation\Livewire\ResetPassword;
use App\Domain\Auth\Presentation\Livewire\TwoFactorAuthenticationForm;
use App\Domain\Auth\Presentation\Livewire\TwoFactorChallenge;
use App\Domain\Auth\Presentation\Livewire\VerifyEmail;
use App\Domain\Auth\Settings\SsoSettings;
use App\Domain\Settings\Services\SettingsRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(SettingsRegistry::class, function (SettingsRegistry $registry): void {
            $registry->register(new SsoSettings);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'auth');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');

        Blade::anonymousComponentPath(__DIR__.'/Presentation/Views/components', 'auth');

        Livewire::addComponent(name: 'auth.accept-invitation', class: AcceptInvitation::class);
        Livewire::addComponent(name: 'auth.confirm-password', class: ConfirmPassword::class);
        Livewire::addComponent(name: 'auth.forgot-password', class: ForgotPassword::class);
        Livewire::addComponent(name: 'auth.login', class: Login::class);
        Livewire::addComponent(name: 'auth.reset-password', class: ResetPassword::class);
        Livewire::addComponent(name: 'auth.two-factor-challenge', class: TwoFactorChallenge::class);
        Livewire::addComponent(name: 'auth.verify-email', class: VerifyEmail::class);
        Livewire::addComponent(name: 'settings.two-factor-authentication-form', class: TwoFactorAuthenticationForm::class);

        Event::listen(SocialiteWasCalled::class, MicrosoftExtendSocialite::class);
    }
}
