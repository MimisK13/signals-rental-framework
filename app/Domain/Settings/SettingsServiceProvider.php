<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Settings\Presentation\Livewire\Admin\Branding;
use App\Domain\Settings\Presentation\Livewire\Admin\Company;
use App\Domain\Settings\Presentation\Livewire\Admin\Email;
use App\Domain\Settings\Presentation\Livewire\Admin\Finance;
use App\Domain\Settings\Presentation\Livewire\Admin\GeneralPreferences;
use App\Domain\Settings\Presentation\Livewire\Admin\Integrations;
use App\Domain\Settings\Presentation\Livewire\Admin\Scheduling;
use App\Domain\Settings\Presentation\Livewire\Admin\Security;
use App\Domain\Settings\Services\SettingsRegistry;
use App\Domain\Settings\Services\SettingsService;
use App\Domain\Settings\Settings\CompanySettings;
use App\Domain\Settings\Settings\EmailSettings;
use App\Domain\Settings\Settings\FinanceSettings;
use App\Domain\Settings\Settings\GeneralPreferencesSettings;
use App\Domain\Settings\Settings\IntegrationSettings;
use App\Domain\Settings\Settings\SchedulingSettings;
use App\Domain\Settings\Settings\SecuritySettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);

        $this->app->singleton(SettingsRegistry::class, function (): SettingsRegistry {
            $registry = new SettingsRegistry;

            $registry->register(new CompanySettings);
            $registry->register(new EmailSettings);
            $registry->register(new FinanceSettings);
            $registry->register(new GeneralPreferencesSettings);
            $registry->register(new IntegrationSettings);
            $registry->register(new SchedulingSettings);
            $registry->register(new SecuritySettings);

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'settings');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Livewire::addComponent(name: 'admin.settings.company', class: Company::class);
        Livewire::addComponent(name: 'admin.settings.branding', class: Branding::class);
        Livewire::addComponent(name: 'admin.settings.email', class: Email::class);
        Livewire::addComponent(name: 'admin.settings.finance', class: Finance::class);
        Livewire::addComponent(name: 'admin.settings.preferences', class: GeneralPreferences::class);
        Livewire::addComponent(name: 'admin.settings.scheduling', class: Scheduling::class);
        Livewire::addComponent(name: 'admin.settings.integrations', class: Integrations::class);
        Livewire::addComponent(name: 'admin.settings.security', class: Security::class);

        if (config('signals.installed')) {
            try {
                app(SettingsService::class)->load();
                $this->configureMailFromSettings();
            } catch (QueryException $e) {
                // Suppress "table not found" during migrations or testing
                // PostgreSQL: SQLSTATE 42P01, SQLite: SQLSTATE HY000 with "no such table"
                $isTableNotFound = $e->getCode() === '42P01'
                    || str_contains($e->getMessage(), 'no such table');

                if (! $isTableNotFound) {
                    logger()->error('SettingsServiceProvider: unexpected database error during boot.', [
                        'exception' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ]);
                }
            }
        }
    }

    /**
     * Override Laravel's mail configuration from stored email settings.
     */
    private function configureMailFromSettings(): void
    {
        $service = app(SettingsService::class);
        $mailer = $service->get('email.mailer');

        if (! $mailer || $mailer === 'log') {
            return;
        }

        config(['mail.default' => $mailer]);

        match ($mailer) {
            'smtp' => config([
                'mail.mailers.smtp.host' => $service->get('email.smtp_host'),
                'mail.mailers.smtp.port' => $service->get('email.smtp_port'),
                'mail.mailers.smtp.username' => $service->get('email.smtp_username'),
                'mail.mailers.smtp.password' => $service->get('email.smtp_password'),
                'mail.mailers.smtp.encryption' => $service->get('email.smtp_encryption') === 'none' ? null : $service->get('email.smtp_encryption'),
            ]),
            'ses' => config([
                'services.ses.key' => $service->get('email.ses_key'),
                'services.ses.secret' => $service->get('email.ses_secret'),
                'services.ses.region' => $service->get('email.ses_region'),
            ]),
            'mailgun' => config([
                'services.mailgun.domain' => $service->get('email.mailgun_domain'),
                'services.mailgun.secret' => $service->get('email.mailgun_secret'),
            ]),
            'postmark' => config([
                'services.postmark.token' => $service->get('email.postmark_token'),
            ]),
            default => logger()->warning("Unknown mail driver configured in settings: {$mailer}"),
        };

        $fromAddress = $service->get('email.from_address');
        $fromName = $service->get('email.from_name');

        if ($fromAddress) {
            config([
                'mail.from.address' => $fromAddress,
                'mail.from.name' => $fromName ?: config('app.name'),
            ]);
        }

        $replyTo = $service->get('email.reply_to_address');

        if ($replyTo) {
            config([
                'mail.reply_to.address' => $replyTo,
                'mail.reply_to.name' => $fromName ?: config('app.name'),
            ]);
        }
    }
}
