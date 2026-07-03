<?php

namespace App\Services\Plugins;

use App\Contracts\Documents\DocumentDataResolver;
use App\Contracts\Documents\PdfDriver;
use App\Contracts\Notifications\ChannelDriver;
use App\Contracts\Notifications\RecipientResolver;
use App\Models\Store;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\DocumentTypeRegistry;
use App\Services\Documents\PdfDriverManager;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Notifications\MergeFieldFilterRegistry;
use App\Services\Notifications\MergeFieldRenderContext;
use App\Services\Notifications\RecipientResolverRegistry;
use Illuminate\Database\Eloquent\Model;

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
}
