<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Projects\Opportunities\Services\Hooks\WorkflowTriggerRegistry;
use App\Models\User;
use App\Policies\NumberingPolicy;
use App\Services\ColumnRegistryResolver;
use App\Services\DocsService;
use App\Services\PermissionRegistry;
use App\Services\Plugins\PluginRegistrar;
use App\Services\SchemaRegistry;
use App\Support\Formatter;
use App\Support\NumberingAccess;
use App\Support\Timezone;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Metadata;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Timezone::class);
        $this->app->singleton(Formatter::class);
        $this->app->singleton(DocsService::class);
        $this->app->singleton(SchemaRegistry::class);
        $this->app->singleton(PluginRegistrar::class);
        $this->app->singleton(ColumnRegistryResolver::class);

        $this->app->singleton(PermissionRegistry::class, function (): PermissionRegistry {
            $registry = new PermissionRegistry;
            $registry->registerMany(PermissionSeeder::permissions());

            return $registry;
        });

    }

    public function boot(): void
    {
        Model::preventLazyLoading();
        // Model::automaticallyEagerLoadRelationships();

        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->registerBladeDirectives();
        $this->registerSocialiteProviders();
        $this->registerVerbsMetadata();
        $this->registerDocumentWorkflowTriggers();

    }

    /**
     * Stamp every Verbs event with the firing actor's context at fire time.
     *
     * The callback runs once per event when it is first fired (reading live
     * auth()/request()), and the resulting metadata is serialised into the
     * verb_events.metadata column at commit. That persisted column is the
     * durable source of actor truth: the audit bridge reads it back (raw, by
     * event id) when projecting, so the ORIGINAL actor is preserved across
     * Verbs::replay() even under console/replay where auth()/request() are null.
     * Tenant-ignorant by design: actor context only, no business logic.
     */
    protected function registerVerbsMetadata(): void
    {
        app(MetadataManager::class)->createMetadataUsing(function (Metadata $metadata): array {
            $request = app()->runningInConsole() ? null : request();

            // Returning an iterable lets the MetadataManager merge() it onto the
            // event's Metadata, sidestepping direct writes to Metadata's magic
            // (undeclared) properties.
            return [
                'user_id' => auth()->id(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ];
        });
    }

    /**
     * Register community Socialite providers.
     *
     * Google ships with Laravel Socialite core, but Microsoft 365 requires the
     * socialiteproviders/microsoft package, which extends Socialite via the
     * SocialiteWasCalled event.
     */
    protected function registerSocialiteProviders(): void
    {
        Event::listen(SocialiteWasCalled::class, MicrosoftExtendSocialite::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): ?Password {
            if (! app()->isProduction()) {
                return null;
            }

            $minLength = max(8, (int) settings('security.password_min_length', 8));
            $rule = Password::min($minLength);

            if (settings('security.password_require_uppercase', false)) {
                $rule->mixedCase();
            }

            if (settings('security.password_require_number', false)) {
                $rule->numbers();
            }

            if (settings('security.password_require_special', false)) {
                $rule->symbols();
            }

            return $rule->uncompromised();
        });
    }

    /**
     * Owner users bypass Spatie permission checks (empty Gate arguments) but not
     * model-policy rules that depend on entity state — those still run so owners
     * cannot issue/edit/delete invoices (etc.) in invalid statuses.
     */
    protected function configureAuthorization(): void
    {
        Gate::policy(NumberingAccess::class, NumberingPolicy::class);

        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            if (! $user->isOwner()) {
                return null;
            }

            return $arguments === [] ? true : null;
        });

        Gate::define('owner', function (User $user): bool {
            return $user->isOwner();
        });
    }

    /**
     * Register Blade directives for localised date/datetime display and permission layers.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('localdate', function (string $expression): string {
            return "<?php echo app(\App\Support\Formatter::class)->date({$expression}); ?>";
        });

        Blade::directive('localdatetime', function (string $expression): string {
            return "<?php echo app(\App\Support\Formatter::class)->dateTime({$expression}); ?>";
        });

        // Permission layer directives
        Blade::directive('area', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->can({$expression})): ?>";
        });

        Blade::directive('endarea', function (): string {
            return '<?php endif; ?>';
        });

        Blade::directive('action', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->can({$expression})): ?>";
        });

        Blade::directive('endaction', function (): string {
            return '<?php endif; ?>';
        });

        Blade::directive('costs', function (): string {
            return "<?php if(auth()->check() && auth()->user()->can('costs.view')): ?>";
        });

        Blade::directive('endcosts', function (): string {
            return '<?php endif; ?>';
        });
    }

    /**
     * Configure non-API application rate limiters.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('vies-validation', function () {
            return Limit::perMinute(30);
        });
    }

    protected function registerDocumentWorkflowTriggers(): void
    {
        $registry = app(WorkflowTriggerRegistry::class);

        foreach ([
            'document.created',
            'document.finalised',
            'document.voided',
            'document.emailed',
            'document.public_link_created',
            'document.public_link_accessed',
            'document.public_link_revoked',
            'document_template.created',
            'document_template.updated',
            'document_template.deleted',
            'document_template.set_as_default',
            'document_sequence.reset',
        ] as $transition) {
            $registry->register($transition, 'core-document-engine-seam');
        }
    }
}
