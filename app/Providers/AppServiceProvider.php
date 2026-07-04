<?php

namespace App\Providers;

use App\Contracts\Availability\AvailabilityDataPresence;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Contracts\Availability\AvailabilityStrategyContract;
use App\Contracts\Documents\DocumentRenderer;
use App\Contracts\Documents\PdfDriver;
use App\Contracts\Tax\TaxResolver;
use App\Contracts\Tax\VatNumberValidator;
use App\Guards\Opportunities\Contracts\ApprovalGate;
use App\Guards\Opportunities\PluginValidatorRegistry;
use App\Guards\Opportunities\Rules\DispatchShortageRule;
use App\Guards\Opportunities\Rules\FxTaxLockRule;
use App\Guards\Opportunities\Rules\ShortageConfirmationRule;
use App\Guards\Opportunities\Stages\AutoApprovalGate;
use App\Listeners\RecordCustomerCommunicationDelivery;
use App\Models\User;
use App\Services\Activities\ActivityTypeList;
use App\Services\Api\AbilityRegistry;
use App\Services\Api\ApiEndpointCatalogue;
use App\Services\Api\CoreAbilityDefinitions;
use App\Services\Api\PriceWeightResolver;
use App\Services\Api\UsageRecorder;
use App\Services\Api\WebhookEventRegistry;
use App\Services\Availability\ContainerDemandResolver;
use App\Services\Availability\DatabaseAvailabilityDataPresence;
use App\Services\Availability\KitAvailabilityCalculator;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\PassThroughAvailabilityStrategy;
use App\Services\Availability\RecalculationPipeline;
use App\Services\Availability\SettingsAvailabilityResolutionProvider;
use App\Services\Availability\SlotCalculator;
use App\Services\Availability\VirtualStockDemandResolver;
use App\Services\AvailabilityService;
use App\Services\ColumnRegistryResolver;
use App\Services\DemandSourceDefinition;
use App\Services\DemandSourceRegistry;
use App\Services\DocsService;
use App\Services\Documents\CreditNoteDataResolver;
use App\Services\Documents\DocumentBrandingResolver;
use App\Services\Documents\DocumentEventDispatcher;
use App\Services\Documents\DocumentRenderContext;
use App\Services\Documents\DocumentRenderPipeline;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\DocumentTypeRegistry;
use App\Services\Documents\InvoiceDataResolver;
use App\Services\Documents\OpportunityDataResolver;
use App\Services\Documents\PdfDriverManager;
use App\Services\Documents\ReceiptDataResolver;
use App\Services\Documents\ReturnListDataResolver;
use App\Services\Documents\SandboxedBladeCompiler;
use App\Services\Documents\SandboxedBladeRenderer;
use App\Services\Documents\StatementDataResolver;
use App\Services\Invoices\InvoiceStatusRegistry;
use App\Services\NotificationRegistry;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Notifications\CommunicationLogStatusRegistry;
use App\Services\Notifications\CommunicationTemplateRenderer;
use App\Services\Notifications\CoreMergeFieldFilters;
use App\Services\Notifications\Drivers\BroadcastDriver;
use App\Services\Notifications\Drivers\DatabaseDriver;
use App\Services\Notifications\Drivers\EmailDriver;
use App\Services\Notifications\EventNotificationConditionEvaluator;
use App\Services\Notifications\EventNotificationMapRegistry;
use App\Services\Notifications\MergeFieldFilterRegistry;
use App\Services\Notifications\MergeFieldRenderer;
use App\Services\Notifications\NotificationChannelResolver;
use App\Services\Notifications\RecipientResolverRegistry;
use App\Services\Notifications\RecipientResolvers\ContactRecipientResolver;
use App\Services\Notifications\RecipientResolvers\RoleRecipientResolver;
use App\Services\Notifications\RecipientResolvers\SpecificMemberRecipientResolver;
use App\Services\Notifications\RecipientResolvers\SubjectOwnerRecipientResolver;
use App\Services\Notifications\RecipientResolvers\TriggerActorRecipientResolver;
use App\Services\Opportunities\AutoPromotionContext;
use App\Services\Opportunities\Hooks\ApprovalChainRegistry;
use App\Services\Opportunities\Hooks\WorkflowTriggerRegistry;
use App\Services\Opportunities\TransitionRuleRegistry;
use App\Services\PermissionRegistry;
use App\Services\Plugins\PluginDocumentTemplateSeederRegistry;
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
use App\Services\Pricing\OpportunityPricingCalculateDecorator;
use App\Services\Pricing\OpportunityPricingRecalculator;
use App\Services\Pricing\Predicates\ProductGroupIdsPredicate;
use App\Services\Pricing\Predicates\ProductIdsPredicate;
use App\Services\Pricing\Predicates\QuantityMaxPredicate;
use App\Services\Pricing\Predicates\QuantityMinPredicate;
use App\Services\Pricing\Predicates\TagNamesPredicate;
use App\Services\Pricing\Predicates\TransactionTypesPredicate;
use App\Services\Pricing\QuantityContextResolver;
use App\Services\RateEngine\Modifiers\FactorModifier;
use App\Services\RateEngine\Modifiers\MultiplierModifier;
use App\Services\RateEngine\RateEngineRegistry;
use App\Services\RateEngine\Strategies\FixedStrategy;
use App\Services\RateEngine\Strategies\HybridStrategy;
use App\Services\RateEngine\Strategies\PeriodStrategy;
use App\Services\SchemaRegistry;
use App\Services\Shortages\CostApportionmentRegistry;
use App\Services\Shortages\NullCostApportionmentStrategy;
use App\Services\Shortages\PipelineShortageEmitter;
use App\Services\Shortages\Resolvers\DateShiftResolver;
use App\Services\Shortages\Resolvers\PartialFulfilmentResolver;
use App\Services\Shortages\Resolvers\QuoteReallocationResolver;
use App\Services\Shortages\Resolvers\SubHireResolver;
use App\Services\Shortages\Resolvers\SubstitutionResolver;
use App\Services\Shortages\Resolvers\WaitlistResolver;
use App\Services\Shortages\Resolvers\WarehouseTransferResolver;
use App\Services\Shortages\ShortageResolverDefinition;
use App\Services\Shortages\ShortageResolverRegistry;
use App\Services\Tax\DefaultVatNumberValidator;
use App\Services\Tax\TaxResolutionContextHolder;
use App\Services\Tax\TaxZoneRuleResolver;
use App\Services\Tax\ZoneAwareTaxResolver;
use App\Services\TaxCalculator;
use App\Support\Formatter;
use App\Support\Timezone;
use Carbon\CarbonImmutable;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Notifications\Events\NotificationSent;
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
        $this->app->singleton(DocumentResolverRegistry::class, function ($app): DocumentResolverRegistry {
            $registry = new DocumentResolverRegistry;
            $registry->register($app->make(OpportunityDataResolver::class));
            $registry->register($app->make(ReturnListDataResolver::class));
            $registry->register($app->make(StatementDataResolver::class));
            $registry->register($app->make(ReceiptDataResolver::class));
            $registry->register($app->make(InvoiceDataResolver::class));
            $registry->register($app->make(CreditNoteDataResolver::class));

            return $registry;
        });
        $this->app->singleton(DocumentTypeRegistry::class);
        $this->app->singleton(DocumentEventDispatcher::class);
        $this->app->scoped(DocumentRenderContext::class);
        $this->app->singleton(PluginDocumentTemplateSeederRegistry::class);
        $this->app->singleton(PluginRegistrar::class);
        $this->app->singleton(SandboxedBladeCompiler::class, function ($app): SandboxedBladeCompiler {
            $cachePath = (string) config('documents.sandbox_cache_path');

            if (! is_dir($cachePath)) {
                mkdir($cachePath, 0755, true);
            }

            return new SandboxedBladeCompiler(
                $app->make(Filesystem::class),
                $cachePath,
                base_path(),
            );
        });
        $this->app->singleton(SandboxedBladeRenderer::class);
        $this->app->singleton(DocumentBrandingResolver::class);
        $this->app->singleton(PdfDriverManager::class);
        $this->app->bind(PdfDriver::class, fn ($app): PdfDriver => $app->make(PdfDriverManager::class)->driver());
        $this->app->singleton(DocumentRenderPipeline::class);
        $this->app->bind(DocumentRenderer::class, DocumentRenderPipeline::class);
        $this->app->singleton(TaxCalculator::class);
        $this->app->scoped(TaxResolutionContextHolder::class);
        $this->app->singleton(TaxZoneRuleResolver::class);
        $this->app->singleton(ZoneAwareTaxResolver::class);
        $this->app->bind(TaxResolver::class, ZoneAwareTaxResolver::class);
        $this->app->singleton(VatNumberValidator::class, DefaultVatNumberValidator::class);
        $this->app->singleton(ColumnRegistryResolver::class);
        $this->app->singleton(ActivityTypeList::class);

        // Request-scoped suppression flag for batch auto-promotion (scoped so it is
        // Octane-safe — reset between requests).
        $this->app->scoped(AutoPromotionContext::class);

        // Availability seams — tenant-ignorant OSS defaults. The commercial Cloud
        // package rebinds these to tenant-/hosting-aware implementations.
        $this->app->bind(AvailabilityDataPresence::class, DatabaseAvailabilityDataPresence::class);
        $this->app->bind(AvailabilityResolutionProvider::class, SettingsAvailabilityResolutionProvider::class);

        // Availability strategy hook seam — the OSS default is a pass-through
        // no-op bracketing the recalculation per-slot calculation (steps 3 & 5).
        // A plugin rebinds this to add buffer stock, sub-hire augmentation, etc.
        $this->app->bind(AvailabilityStrategyContract::class, PassThroughAvailabilityStrategy::class);

        // Availability engine read/recalc services. The SlotCalculator reads the
        // resolution live through its provider, so a singleton is safe.
        $this->app->singleton(SlotCalculator::class);
        $this->app->singleton(PipelineShortageEmitter::class);
        // Scoped (not singleton): the pipeline memoises store timezones on the
        // instance, so under Octane it must be reset each request/job rather than
        // persisting a stale timezone for the worker's lifetime.
        $this->app->scoped(RecalculationPipeline::class);
        $this->app->singleton(AvailabilityService::class);

        // The kit calculator reads component availability back through the
        // AvailabilityService. Resolve the service lazily (closure) so the
        // singleton never captures a request-bound dependency in its constructor
        // (Octane-safe) and so tests can rebind the service to a mock.
        $this->app->singleton(KitAvailabilityCalculator::class, fn ($app): KitAvailabilityCalculator => new KitAvailabilityCalculator(
            fn (): AvailabilityService => $app->make(AvailabilityService::class),
        ));

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

        $this->app->singleton(ChannelProviderRegistry::class, function ($app): ChannelProviderRegistry {
            $registry = new ChannelProviderRegistry($app);

            $registry->register('email', EmailDriver::class);
            $registry->register('database', DatabaseDriver::class);
            $registry->register('broadcast', BroadcastDriver::class);

            return $registry;
        });

        $this->app->singleton(CommunicationLogStatusRegistry::class);
        $this->app->singleton(InvoiceStatusRegistry::class);

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

        $this->app->singleton(MergeFieldFilterRegistry::class, function (): MergeFieldFilterRegistry {
            $registry = new MergeFieldFilterRegistry;
            CoreMergeFieldFilters::register($registry);

            return $registry;
        });

        $this->app->singleton(MergeFieldRenderer::class);
        $this->app->singleton(CommunicationTemplateRenderer::class);

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

        $this->app->singleton(DemandSourceRegistry::class, function (): DemandSourceRegistry {
            $registry = new DemandSourceRegistry;

            $registry->register(new DemandSourceDefinition(
                type: 'opportunity_item',
                displayName: 'Bookings',
                resolverClass: OpportunityItemDemandResolver::class,
                colour: '#3B82F6',
                icon: 'calendar',
            ));

            // Container reservations: serialised items held indefinitely inside a
            // kit/hybrid-fixed container, removing them from individual availability
            // (serialised-containers.md §"Kit Mode — Contents Removed from
            // Availability"). Created on pack, voided on unpack.
            $registry->register(new DemandSourceDefinition(
                type: 'container',
                displayName: 'Containers',
                resolverClass: ContainerDemandResolver::class,
                colour: '#0EA5E9',
                icon: 'cube',
            ));

            $registry->register(new DemandSourceDefinition(
                type: 'virtual_stock_intake',
                displayName: 'Virtual Stock',
                resolverClass: VirtualStockDemandResolver::class,
                colour: '#8B5CF6',
                icon: 'arrow-down-circle',
            ));

            return $registry;
        });

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
        $this->app->singleton(OpportunityPricingCalculateDecorator::class);
        $this->app->singleton(DealPriceProportionalDistributor::class);
        $this->app->singleton(OpportunityPricingRecalculator::class);

        $this->app->singleton(RateEngineRegistry::class, function (): RateEngineRegistry {
            $registry = new RateEngineRegistry;

            $registry->registerStrategy(new PeriodStrategy);
            $registry->registerStrategy(new FixedStrategy);
            $registry->registerStrategy(new HybridStrategy);

            $registry->registerModifier(new MultiplierModifier);
            $registry->registerModifier(new FactorModifier);

            return $registry;
        });

        // The substitution resolver memoises its `product_substitutions` schema
        // check on the instance; bind it as a singleton so one detection pass
        // (resolved transiently from the registry) shares a single lookup.
        $this->app->singleton(SubstitutionResolver::class);

        // Shortage resolvers — core registers the built-in non-PO resolvers;
        // plugins register their own (and the sub-hire/external-sourcing
        // resolvers land in Phase 4) through the same interface.
        $this->app->singleton(ShortageResolverRegistry::class, function (): ShortageResolverRegistry {
            $registry = new ShortageResolverRegistry;

            $registry->register(new ShortageResolverDefinition('reallocate', QuoteReallocationResolver::class));
            $registry->register(new ShortageResolverDefinition('substitute', SubstitutionResolver::class));
            $registry->register(new ShortageResolverDefinition('transfer', WarehouseTransferResolver::class));
            $registry->register(new ShortageResolverDefinition('date_shift', DateShiftResolver::class));
            $registry->register(new ShortageResolverDefinition('partial', PartialFulfilmentResolver::class));
            $registry->register(new ShortageResolverDefinition('waitlist', WaitlistResolver::class));
            $registry->register(new ShortageResolverDefinition('subhire', SubHireResolver::class));

            return $registry;
        });

        // Cost apportionment — STUB for later sub-hire (Phase 4). Ships the no-op
        // strategy only; virtual stock and plugins register the real strategies.
        $this->app->singleton(CostApportionmentRegistry::class, function (): CostApportionmentRegistry {
            $registry = new CostApportionmentRegistry;

            $registry->register(new NullCostApportionmentStrategy);

            return $registry;
        });

        $this->registerOpportunityGuardPipeline();
    }

    /**
     * Register the opportunity transition guard pipeline (opportunity-lifecycle.md
     * §12.2). The pipeline composes four ordered stages — Permission (real),
     * Approval (placeholder seam), Business Rules (config-driven registry), and
     * Plugin Validators (placeholder seam) — that actions run before firing a
     * transition's Verbs event(s); Verbs `validate()` remains the final
     * hard-invariant layer.
     */
    protected function registerOpportunityGuardPipeline(): void
    {
        // Approval stage seam — PLACEHOLDER. The OSS default always approves; a
        // future approval-chain consumer rebinds this contract with no retrofit.
        $this->app->singleton(AutoApprovalGate::class);
        $this->app->bind(ApprovalGate::class, AutoApprovalGate::class);

        // Plugin-validator stage seam — PLACEHOLDER. Empty in core; plugins
        // register TransitionRule validators through it via the Plugin SDK.
        $this->app->singleton(PluginValidatorRegistry::class);

        // Unbuilt-consumer hooks — PLACEHOLDERS, empty in core. They let the
        // workflow + approval-chain engines attach to the opportunity event stream
        // with no retrofit (the notification consumer uses the NotificationRegistry
        // above). Consulted nowhere in the hot path today.
        $this->app->singleton(WorkflowTriggerRegistry::class);
        $this->app->singleton(ApprovalChainRegistry::class);

        // Business-rules stage registry — core registers the concrete config-driven
        // rules; plugins register their own through the same interface. Rules are
        // matched generically by transition (never a hardcoded status matrix).
        $this->app->singleton(TransitionRuleRegistry::class, function ($app): TransitionRuleRegistry {
            $registry = new TransitionRuleRegistry;

            // The shortage confirmation gate (quote → order): auto-resolve + the
            // store shortage policy relaxed by `shortages.ignore`.
            $registry->register($app->make(ShortageConfirmationRule::class));

            // FX/tax lock enforcement: a rate/tax edit on a locked (confirmed)
            // order is rejected until the locks are released.
            $registry->register($app->make(FxTaxLockRule::class));

            // Dispatch shortage policy — precheck-only: surfaces the store dispatch
            // policy Block in `available_actions`. Write-time enforcement stays in
            // the per-line DispatchShortageGate calls inside the dispatch actions.
            $registry->register($app->make(DispatchShortageRule::class));

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->registerBladeDirectives();
        $this->registerSocialiteProviders();
        $this->registerVerbsMetadata();
        $this->registerApiCatalogueCacheInvalidation();
        $this->registerPricingWorkflowTriggers();
        $this->registerDocumentWorkflowTriggers();
        $this->registerCustomerCommunicationListeners();

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

    protected function registerCustomerCommunicationListeners(): void
    {
        Event::listen(
            NotificationSent::class,
            [RecordCustomerCommunicationDelivery::class, 'handle'],
        );
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
     * Owner users bypass all permission checks.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->isOwner() ? true : null;
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
            'opportunity.pricing_recalculated',
            'opportunity.discount_reset',
        ] as $transition) {
            $registry->register($transition, 'core-pricing-seam');
        }
    }
}
