<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Contracts\DocumentRenderer;
use App\Domain\Documents\Contracts\PdfDriver;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Policies\DocumentPolicy;
use App\Domain\Documents\Policies\DocumentTemplatePolicy;
use App\Domain\Documents\Presentation\Livewire\Editor;
use App\Domain\Documents\Presentation\Livewire\Index;
use App\Domain\Documents\Presentation\Livewire\OpportunityDocuments;
use App\Domain\Documents\Services\CreditNoteDataResolver;
use App\Domain\Documents\Services\DocumentBrandingResolver;
use App\Domain\Documents\Services\DocumentEventDispatcher;
use App\Domain\Documents\Services\DocumentRenderContext;
use App\Domain\Documents\Services\DocumentRenderPipeline;
use App\Domain\Documents\Services\DocumentResolverRegistry;
use App\Domain\Documents\Services\DocumentTypeRegistry;
use App\Domain\Documents\Services\InvoiceDataResolver;
use App\Domain\Documents\Services\OpportunityDataResolver;
use App\Domain\Documents\Services\PdfDriverManager;
use App\Domain\Documents\Services\PluginDocumentTemplateSeederRegistry;
use App\Domain\Documents\Services\PurchaseOrderDataResolver;
use App\Domain\Documents\Services\ReceiptDataResolver;
use App\Domain\Documents\Services\ReturnListDataResolver;
use App\Domain\Documents\Services\SandboxedBladeCompiler;
use App\Domain\Documents\Services\SandboxedBladeRenderer;
use App\Domain\Documents\Services\StatementDataResolver;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class DocumentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DocumentResolverRegistry::class, function ($app): DocumentResolverRegistry {
            $registry = new DocumentResolverRegistry;
            $registry->register($app->make(OpportunityDataResolver::class));
            $registry->register($app->make(ReturnListDataResolver::class));
            $registry->register($app->make(StatementDataResolver::class));
            $registry->register($app->make(ReceiptDataResolver::class));
            $registry->register($app->make(InvoiceDataResolver::class));
            $registry->register($app->make(CreditNoteDataResolver::class));
            $registry->register($app->make(PurchaseOrderDataResolver::class));

            return $registry;
        });
        $this->app->singleton(DocumentTypeRegistry::class);
        $this->app->singleton(DocumentEventDispatcher::class);
        $this->app->scoped(DocumentRenderContext::class);
        $this->app->singleton(PluginDocumentTemplateSeederRegistry::class);
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
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'documents');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(DocumentTemplate::class, DocumentTemplatePolicy::class);

        Livewire::addComponent(name: 'document-templates.index', class: Index::class);
        Livewire::addComponent(name: 'document-templates.edit', class: Editor::class);
        Livewire::addComponent(name: 'opportunities.documents', class: OpportunityDocuments::class);
    }
}
