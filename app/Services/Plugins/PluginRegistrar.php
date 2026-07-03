<?php

namespace App\Services\Plugins;

use App\Contracts\Documents\DocumentDataResolver;
use App\Contracts\Documents\PdfDriver;
use App\Models\Store;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\DocumentTypeRegistry;
use App\Services\Documents\PdfDriverManager;
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
}
