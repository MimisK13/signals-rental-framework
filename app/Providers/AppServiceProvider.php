<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Projects\Opportunities\Services\Hooks\WorkflowTriggerRegistry;
use App\Models\User;
use App\Policies\NumberingPolicy;
use App\Services\Api\AbilityRegistry;
use App\Services\Api\ApiEndpointCatalogue;
use App\Services\Api\CoreAbilityDefinitions;
use App\Services\Api\PriceWeightResolver;
use App\Services\Api\UsageRecorder;
use App\Services\Api\WebhookEventRegistry;
use App\Services\ColumnRegistryResolver;
use App\Services\DocsService;
use App\Services\NotificationRegistry;
use App\Services\Notifications\EventNotificationConditionEvaluator;
use App\Services\Notifications\EventNotificationMapRegistry;
use App\Services\Notifications\NotificationChannelResolver;
use App\Services\Notifications\RecipientResolverRegistry;
use App\Services\Notifications\RecipientResolvers\ContactRecipientResolver;
use App\Services\Notifications\RecipientResolvers\RoleRecipientResolver;
use App\Services\Notifications\RecipientResolvers\SpecificMemberRecipientResolver;
use App\Services\Notifications\RecipientResolvers\SubjectOwnerRecipientResolver;
use App\Services\Notifications\RecipientResolvers\TriggerActorRecipientResolver;
use App\Services\PermissionRegistry;
use App\Services\Plugins\PluginRegistrar;
use App\Services\Pricing\BundlePricingApplicator;
use App\Services\Pricing\CriteriaMatcher;
use App\Services\Pricing\DealPriceProportionalDistributor;
use App\Services\Pricing\DealPriceValidatorRegistry;
use App\Services\Pricing\DiscountCategoryApplier;
use App\Services\Pricing\DiscountCriteriaPredicateRegistry;
use App\Services\Pricing\DiscountRuleEvaluator;
use App\Services\Pricing\DiscountSourceRegistry;
use App\Services\Pricing\OpportunityItemDiscountPercentFilterRegistry;
use App\Services\Pricing\OpportunityItemSurchargeAmountFilterRegistry;
use App\Services\Pricing\OpportunityPricingCalculateDecorator;
use App\Services\Pricing\OpportunityPricingRecalculator;
use App\Services\Pricing\Predicates\ProductGroupIdsPredicate;
use App\Services\Pricing\Predicates\ProductIdsPredicate;
use App\Services\Pricing\Predicates\QuantityMaxPredicate;
use App\Services\Pricing\Predicates\QuantityMinPredicate;
use App\Services\Pricing\Predicates\TagNamesPredicate;
use App\Services\Pricing\Predicates\TransactionTypesPredicate;
use App\Services\Pricing\QuantityContextResolver;
use App\Services\SchemaRegistry;
use App\Support\Formatter;
use App\Support\NumberingAccess;
use App\Support\Timezone;
use Carbon\CarbonImmutable;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;
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

        $this->app->singleton(NotificationRegistry::class, function (): NotificationRegistry {
            $registry = new NotificationRegistry;
            $registry->registerMany(NotificationTypeSeeder::types());

            return $registry;
        });

        $this->app->singleton(EventNotificationMapRegistry::class);

        $this->app->singleton(EventNotificationConditionEvaluator::class);

        $this->app->singleton(NotificationChannelResolver::class);

        $this->app->singleton(RecipientResolverRegistry::class, function (): RecipientResolverRegistry {
            $registry = new RecipientResolverRegistry;

            $registry->register(new SubjectOwnerRecipientResolver);
            $registry->register(new RoleRecipientResolver);
            $registry->register(new SpecificMemberRecipientResolver);
            $registry->register(new TriggerActorRecipientResolver);
            $registry->register(new ContactRecipientResolver);

            return $registry;
        });

        $this->app->singleton(AbilityRegistry::class, function (): AbilityRegistry {
            $registry = new AbilityRegistry;
            $registry->registerMany(CoreAbilityDefinitions::all());

            return $registry;
        });

        $this->app->singleton(WebhookEventRegistry::class, function (): WebhookEventRegistry {
            $registry = new WebhookEventRegistry;

            foreach (WebhookEventRegistry::CORE_EVENT_NAMES as $name) {
                $registry->register($name);
            }

            return $registry;
        });

        $this->app->singleton(PriceWeightResolver::class);
        $this->app->singleton(ApiEndpointCatalogue::class);
        $this->app->singleton(UsageRecorder::class);

        $this->app->singleton(DiscountCriteriaPredicateRegistry::class, function (): DiscountCriteriaPredicateRegistry {
            $registry = new DiscountCriteriaPredicateRegistry;

            $registry->register(new ProductGroupIdsPredicate);
            $registry->register(new QuantityMinPredicate);
            $registry->register(new QuantityMaxPredicate);
            $registry->register(new ProductIdsPredicate);
            $registry->register(new TagNamesPredicate);
            $registry->register(new TransactionTypesPredicate);

            return $registry;
        });

        $this->app->singleton(CriteriaMatcher::class);
        $this->app->singleton(BundlePricingApplicator::class);
        $this->app->singleton(QuantityContextResolver::class);
        $this->app->singleton(DiscountRuleEvaluator::class);
        $this->app->singleton(DiscountCategoryApplier::class);

        $this->app->singleton(DealPriceValidatorRegistry::class);
        $this->app->singleton(DiscountSourceRegistry::class);
        $this->app->singleton(OpportunityItemDiscountPercentFilterRegistry::class);
        $this->app->singleton(OpportunityItemSurchargeAmountFilterRegistry::class);
        $this->app->singleton(OpportunityPricingCalculateDecorator::class);
        $this->app->singleton(DealPriceProportionalDistributor::class);
        $this->app->singleton(OpportunityPricingRecalculator::class);

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
        $this->registerApiCatalogueCacheInvalidation();
        $this->registerPricingWorkflowTriggers();
        $this->registerDocumentWorkflowTriggers();

        // LogAction is auto-discovered by Laravel via its handle(AuditableEvent) type-hint.
        // Manual registration removed to prevent duplicate listener execution.
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

    protected function registerApiCatalogueCacheInvalidation(): void
    {
        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            if ($event->command !== 'route:clear') {
                return;
            }

            app(ApiEndpointCatalogue::class)->invalidateAll();
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
     * Configure API rate limiting using settings-driven limits.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function ($request) {
            try {
                $limit = max((int) settings('api.rate_limit', 60), 1);
                $unauthLimit = max((int) settings('api.rate_limit_unauthenticated', 20), 1);
            } catch (\Throwable) {
                $limit = 60;
                $unauthLimit = 20;
            }

            if (! $request->user()) {
                return Limit::perMinute($unauthLimit)->by($request->ip());
            }

            $token = $request->user()->currentAccessToken();

            if ($token instanceof PersonalAccessToken && $token->rate_limit_per_minute) {
                return Limit::perMinute($token->rate_limit_per_minute)->by('token:'.$token->id);
            }

            return Limit::perMinute($limit)->by($request->user()->id);
        });

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

    protected function registerPricingWorkflowTriggers(): void
    {
        $registry = app(WorkflowTriggerRegistry::class);

        foreach ([
            'opportunity.deal_price_set',
            'opportunity.deal_price_cleared',
            'opportunity.group_deal_price_set',
            'opportunity.group_deal_price_cleared',
            'opportunity.pricing_recalculated',
            'opportunity.discount_reset',
        ] as $transition) {
            $registry->register($transition, 'core-pricing-seam');
        }
    }
}
