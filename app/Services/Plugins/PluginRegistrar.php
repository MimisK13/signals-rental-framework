<?php

namespace App\Services\Plugins;

use App\Contracts\Documents\DocumentDataResolver;
use App\Contracts\Documents\PdfDriver;
use App\Contracts\Notifications\ChannelDriver;
use App\Contracts\Notifications\RecipientResolver;
use App\Events\Containers\BeforeContainerDispatched;
use App\Events\Containers\BeforeContainerDissolved;
use App\Events\Containers\BeforeContainerItemTransferred;
use App\Events\Containers\BeforeContainerPacked;
use App\Events\Containers\BeforeContainerRepack;
use App\Events\Containers\BeforeContainerSealed;
use App\Events\Containers\ContainerDispatchedLifecycle;
use App\Events\Containers\ContainerDissolved;
use App\Events\Containers\ContainerItemTransferred;
use App\Events\Containers\ContainerPacked;
use App\Events\Containers\ContainerRepackStarted;
use App\Events\Containers\ContainerSealed;
use App\Models\Store;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\DocumentTypeRegistry;
use App\Services\Documents\PdfDriverManager;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Notifications\MergeFieldFilterRegistry;
use App\Services\Notifications\MergeFieldRenderContext;
use App\Services\Notifications\RecipientResolverRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Fluent registration API for document-engine plugin extension points (P22).
 *
 * Plugins call these methods from their {@code register()} lifecycle hook.
 * Additional Plugin SDK capabilities (routes, permissions, widgets) land in
 * future phases; this registrar covers the Document Template Engine seams only.
 */
class PluginRegistrar
{
    public function __construct(
        private readonly DocumentTypeRegistry $documentTypes,
        private readonly DocumentResolverRegistry $resolvers,
        private readonly PdfDriverManager $pdfDrivers,
        private readonly PluginDocumentTemplateSeederRegistry $templateSeeders,
        private readonly ChannelProviderRegistry $channelProviders,
        private readonly RecipientResolverRegistry $recipientResolvers,
        private readonly MergeFieldFilterRegistry $mergeFieldFilters,
    ) {}

    /**
     * Register a custom document type backed by an existing or plugin resolver.
     *
     * @param  class-string  $modelType
     */
    public function documentType(
        string $documentType,
        string $resolverType,
        string $modelType,
        string $label,
        string $description = '',
    ): self {
        $this->documentTypes->register($documentType, $resolverType, $modelType, $label, $description);

        return $this;
    }

    public function documentResolver(DocumentDataResolver $resolver): self
    {
        $this->resolvers->register($resolver);

        return $this;
    }

    /**
     * Inject additional merge-field data after a resolver's core {@see resolve()} output.
     *
     * @param  callable(array<string, mixed>, Model): array<string, mixed>  $extender
     */
    public function extendDocumentResolver(string $resolverType, callable $extender): self
    {
        $this->resolvers->extendResolve($resolverType, $extender);

        return $this;
    }

    /**
     * @param  class-string<PdfDriver>  $driverClass
     */
    public function pdfDriver(string $name, string $driverClass): self
    {
        $this->pdfDrivers->register($name, $driverClass);

        return $this;
    }

    /**
     * Register a callback that seeds plugin-owned templates when a store is created.
     *
     * @param  callable(Store): void  $seeder
     */
    public function seedDocumentTemplates(callable $seeder): self
    {
        $this->templateSeeders->register($seeder);

        return $this;
    }

    /**
     * Register a custom notification channel driver (P23 plugin seam).
     *
     * @param  class-string<ChannelDriver>  $driverClass
     */
    public function channelDriver(string $key, string $driverClass): self
    {
        $this->channelProviders->register($key, $driverClass);

        return $this;
    }

    /**
     * Register a custom notification recipient resolver (P24 plugin seam).
     */
    public function recipientResolver(RecipientResolver $resolver): self
    {
        $this->recipientResolvers->register($resolver);

        return $this;
    }

    /**
     * Register a custom merge-field filter for communication templates (P26 plugin seam).
     *
     * @param  callable(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed  $filter
     */
    public function registerFilter(string $name, callable $filter): self
    {
        $this->mergeFieldFilters->register($name, $filter);

        return $this;
    }

    /**
     * Register a listener for `container.before_pack` (may veto via ValidationException).
     *
     * @param  callable(BeforeContainerPacked): void  $listener
     */
    public function onContainerBeforePack(callable $listener): self
    {
        Event::listen(BeforeContainerPacked::class, $listener);

        return $this;
    }

    /**
     * @param  callable(ContainerPacked): void  $listener
     */
    public function onContainerAfterPack(callable $listener): self
    {
        Event::listen(ContainerPacked::class, $listener);

        return $this;
    }

    /**
     * @param  callable(BeforeContainerSealed): void  $listener
     */
    public function onContainerBeforeSeal(callable $listener): self
    {
        Event::listen(BeforeContainerSealed::class, $listener);

        return $this;
    }

    /**
     * @param  callable(ContainerSealed): void  $listener
     */
    public function onContainerAfterSeal(callable $listener): self
    {
        Event::listen(ContainerSealed::class, $listener);

        return $this;
    }

    /**
     * @param  callable(BeforeContainerDissolved): void  $listener
     */
    public function onContainerBeforeDissolve(callable $listener): self
    {
        Event::listen(BeforeContainerDissolved::class, $listener);

        return $this;
    }

    /**
     * @param  callable(ContainerDissolved): void  $listener
     */
    public function onContainerAfterDissolve(callable $listener): self
    {
        Event::listen(ContainerDissolved::class, $listener);

        return $this;
    }

    /**
     * @param  callable(BeforeContainerDispatched): void  $listener
     */
    public function onContainerBeforeDispatch(callable $listener): self
    {
        Event::listen(BeforeContainerDispatched::class, $listener);

        return $this;
    }

    /**
     * @param  callable(ContainerDispatchedLifecycle): void  $listener
     */
    public function onContainerAfterDispatch(callable $listener): self
    {
        Event::listen(ContainerDispatchedLifecycle::class, $listener);

        return $this;
    }

    /**
     * @param  callable(BeforeContainerRepack): void  $listener
     */
    public function onContainerBeforeRepack(callable $listener): self
    {
        Event::listen(BeforeContainerRepack::class, $listener);

        return $this;
    }

    /**
     * @param  callable(ContainerRepackStarted): void  $listener
     */
    public function onContainerAfterRepack(callable $listener): self
    {
        Event::listen(ContainerRepackStarted::class, $listener);

        return $this;
    }

    /**
     * @param  callable(BeforeContainerItemTransferred): void  $listener
     */
    public function onContainerBeforeTransfer(callable $listener): self
    {
        Event::listen(BeforeContainerItemTransferred::class, $listener);

        return $this;
    }

    /**
     * @param  callable(ContainerItemTransferred): void  $listener
     */
    public function onContainerAfterTransfer(callable $listener): self
    {
        Event::listen(ContainerItemTransferred::class, $listener);

        return $this;
    }
}
