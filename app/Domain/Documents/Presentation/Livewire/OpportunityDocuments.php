<?php

declare(strict_types=1);

namespace App\Domain\Documents\Presentation\Livewire;

use App\Domain\Attachments\Services\FileService;
use App\Domain\Documents\Actions\ConfigureDocumentPublicLink;
use App\Domain\Documents\Actions\CreateDocumentPublicLink;
use App\Domain\Documents\Actions\GenerateDocument;
use App\Domain\Documents\Actions\RevokeDocumentPublicLink;
use App\Domain\Documents\Data\ConfigureDocumentPublicLinkData;
use App\Domain\Documents\Data\CreateDocumentPublicLinkData;
use App\Domain\Documents\Data\GenerateDocumentData;
use App\Domain\Documents\Enums\DocumentOutputFormat;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\QrCodeGenerator;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Presentation\Concerns\HasOpportunityActions;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\LaravelData\Optional;

#[Layout('components.layouts.app')] final class OpportunityDocuments extends Component
{
    use HasOpportunityActions;

    public Opportunity $opportunity;

    public ?int $selectedTemplateId = null;

    public string $outputFormat = 'pdf';

    public ?int $shareDocumentId = null;

    public bool $shareLinkExists = false;

    public ?string $shareExpiresAt = null;

    public ?string $sharePin = null;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');
        Gate::authorize('documents.view');

        $this->opportunity = $opportunity->loadCount('documents');
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Documents');
    }

    public function generateDocument(): void
    {
        Gate::authorize('documents.create');

        $this->validate([
            'selectedTemplateId' => ['required', 'integer', 'exists:document_templates,id'],
            'outputFormat' => ['required', 'in:pdf,html_download'],
        ]);

        try {
            app(GenerateDocument::class)(new GenerateDocumentData(
                document_template_id: $this->selectedTemplateId,
                documentable_id: $this->opportunity->id,
                documentable_type: 'opportunity',
                output_format: DocumentOutputFormat::from($this->outputFormat),
            ));

            $this->opportunity->loadCount('documents');
            $this->reset(['selectedTemplateId', 'outputFormat']);
            $this->outputFormat = 'pdf';

            $this->dispatch('toast', type: 'success', message: __('Document generated successfully.'));
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());
        }
    }

    public function openShareModal(int $documentId): void
    {
        Gate::authorize('documents.share');

        $document = Document::query()->findOrFail($documentId);
        Gate::authorize('share', $document);

        $this->shareDocumentId = $documentId;
        $this->shareLinkExists = is_string($document->public_token) && $document->public_token !== '';
        $this->shareExpiresAt = $document->public_expires_at?->format('Y-m-d\TH:i');
        $this->sharePin = null;
    }

    public function saveShareLink(): void
    {
        if ($this->shareLinkExists) {
            $this->configureShareLink();

            return;
        }

        $this->createShareLink();
    }

    public function createShareLink(): void
    {
        Gate::authorize('documents.share');

        if ($this->shareDocumentId === null) {
            return;
        }

        $document = Document::query()->findOrFail($this->shareDocumentId);
        Gate::authorize('share', $document);

        $this->validate([
            'shareExpiresAt' => ['nullable', 'date'],
            'sharePin' => ['nullable', 'string', 'min:4', 'max:32'],
        ]);

        app(CreateDocumentPublicLink::class)($document, new CreateDocumentPublicLinkData(
            expires_at: $this->shareExpiresAt,
            pin: $this->sharePin,
        ));

        $this->shareDocumentId = null;
        $this->shareLinkExists = false;
        $this->dispatch('toast', type: 'success', message: __('Public link created.'));
    }

    public function revokeShareLink(int $documentId): void
    {
        Gate::authorize('documents.share');

        $document = Document::query()->findOrFail($documentId);
        app(RevokeDocumentPublicLink::class)($document);

        $this->dispatch('toast', type: 'success', message: __('Public link revoked.'));
    }

    public function configureShareLink(): void
    {
        Gate::authorize('documents.share');

        if ($this->shareDocumentId === null) {
            return;
        }

        $document = Document::query()->findOrFail($this->shareDocumentId);

        $this->validate([
            'shareExpiresAt' => ['nullable', 'date'],
            'sharePin' => ['nullable', 'string', 'min:4', 'max:32'],
        ]);

        app(ConfigureDocumentPublicLink::class)($document, new ConfigureDocumentPublicLinkData(
            expires_at: $this->shareExpiresAt,
            pin: $this->sharePin !== '' ? $this->sharePin : new Optional,
        ));

        $this->shareDocumentId = null;
        $this->shareLinkExists = false;
        $this->dispatch('toast', type: 'success', message: __('Public link updated.'));
    }

    public function cancelShareModal(): void
    {
        $this->shareDocumentId = null;
        $this->shareLinkExists = false;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $documents = $this->opportunity->documents()
            ->with('documentTemplate')
            ->latest('created_at')
            ->get();

        $templates = DocumentTemplate::query()
            ->where('store_id', $this->opportunity->store_id)
            ->where('is_active', true)
            ->orderBy('document_type')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return [
            ...$this->opportunityActionData(),
            'documents' => $documents,
            'templates' => $templates,
            'canCreate' => Gate::allows('documents.create'),
            'canShare' => Gate::allows('documents.share'),
            'fileService' => app(FileService::class),
            'qrCodeGenerator' => app(QrCodeGenerator::class),
        ];
    }

    public function render(): View
    {
        return view('documents::opportunity-documents');
    }
}
