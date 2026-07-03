<?php

use App\Actions\Documents\ConfigureDocumentPublicLink;
use App\Actions\Documents\CreateDocumentPublicLink;
use App\Actions\Documents\GenerateDocument;
use App\Actions\Documents\RevokeDocumentPublicLink;
use App\Data\Documents\ConfigureDocumentPublicLinkData;
use App\Data\Documents\CreateDocumentPublicLinkData;
use App\Data\Documents\GenerateDocumentData;
use App\Enums\DocumentOutputFormat;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Opportunity;
use App\Services\FileService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Livewire\Concerns\HasOpportunityActions;

new #[Layout('components.layouts.app')] class extends Component
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
            pin: $this->sharePin !== '' ? $this->sharePin : new \Spatie\LaravelData\Optional,
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
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Documents', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'documents'])

    <div class="flex-1 space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        @if($canCreate)
            <x-signals.card>
                <h3 class="text-sm font-semibold text-[var(--text-secondary)]" style="font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em;">
                    {{ __('Generate document') }}
                </h3>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label class="s-label" for="selectedTemplateId">{{ __('Template') }}</label>
                        <select id="selectedTemplateId" wire:model="selectedTemplateId" class="s-input mt-1 w-full">
                            <option value="">{{ __('Select a template…') }}</option>
                            @foreach($templates as $template)
                                <option wire:key="template-option-{{ $template->id }}" value="{{ $template->id }}">
                                    {{ $template->name }} ({{ $template->document_type }})
                                </option>
                            @endforeach
                        </select>
                        @error('selectedTemplateId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="s-label" for="outputFormat">{{ __('Output') }}</label>
                        <select id="outputFormat" wire:model="outputFormat" class="s-input mt-1 w-full">
                            <option value="pdf">PDF</option>
                            <option value="html_download">HTML</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="button" wire:click="generateDocument" wire:loading.attr="disabled" class="s-btn s-btn-primary">
                        <span wire:loading.remove wire:target="generateDocument">{{ __('Generate') }}</span>
                        <span wire:loading wire:target="generateDocument">{{ __('Generating…') }}</span>
                    </button>
                </div>
            </x-signals.card>
        @endif

        @if($documents->isNotEmpty())
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>{{ __('Number') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Created') }}</th>
                            <th>{{ __('First viewed') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($documents as $document)
                            @php
                                $downloadPath = $document->pdf_path ?? $document->html_snapshot_path;
                                $downloadUrl = $fileService->signedUrlOrNull($downloadPath);
                                $publicUrl = $document->public_token
                                    ? route('documents.public.show', ['token' => $document->public_token])
                                    : null;
                            @endphp
                            <tr wire:key="document-{{ $document->id }}">
                                <td style="font-family: var(--font-mono);">{{ $document->document_number }}</td>
                                <td>{{ $document->document_type }}</td>
                                <td><span class="s-badge s-badge-zinc">{{ ucfirst($document->status->value) }}</span></td>
                                <td>@localdatetime($document->created_at)</td>
                                <td>
                                    @if($document->first_viewed_at)
                                        @localdatetime($document->first_viewed_at)
                                    @else
                                        <span class="text-[var(--text-muted)]">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if($downloadUrl)
                                            <a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="s-btn s-btn-sm s-btn-outline">
                                                {{ __('Download') }}
                                            </a>
                                        @endif
                                        @if($canShare)
                                            @if($publicUrl)
                                                <button type="button"
                                                        x-data="{ copied: false }"
                                                        x-on:click="navigator.clipboard.writeText(@js($publicUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                                                        class="s-btn s-btn-sm s-btn-outline">
                                                    <span x-show="!copied">{{ __('Copy link') }}</span>
                                                    <span x-cloak x-show="copied">{{ __('Copied') }}</span>
                                                </button>
                                                <button type="button" wire:click="openShareModal({{ $document->id }})" class="s-btn s-btn-sm s-btn-outline">
                                                    {{ __('Configure') }}
                                                </button>
                                                <button type="button"
                                                        wire:click="revokeShareLink({{ $document->id }})"
                                                        wire:confirm="{{ __('Revoke this public link?') }}"
                                                        class="s-btn s-btn-sm s-btn-outline-red">
                                                    {{ __('Revoke') }}
                                                </button>
                                            @else
                                                <button type="button" wire:click="openShareModal({{ $document->id }})" class="s-btn s-btn-sm s-btn-outline">
                                                    {{ __('Share') }}
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-signals.table-wrap>
        @else
            <x-signals.empty
                title="{{ __('No documents') }}"
                description="{{ __('Generate a document from a template to share or download it.') }}"
            />
        @endif
    </div>

    @if($shareDocumentId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <x-signals.card class="w-full max-w-md">
                <h3 class="text-base font-semibold text-[var(--text-primary)]">{{ __('Public link settings') }}</h3>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="s-label" for="shareExpiresAt">{{ __('Expires at (optional)') }}</label>
                        <input id="shareExpiresAt" type="datetime-local" wire:model="shareExpiresAt" class="s-input mt-1 w-full" />
                    </div>
                    <div>
                        <label class="s-label" for="sharePin">{{ __('PIN (optional)') }}</label>
                        <input id="sharePin" type="password" wire:model="sharePin" class="s-input mt-1 w-full" autocomplete="new-password" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelShareModal" class="s-btn s-btn-outline">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="saveShareLink" class="s-btn s-btn-primary">{{ __('Save link') }}</button>
                </div>
            </x-signals.card>
        </div>
    @endif

    @include('livewire.opportunities.partials.opportunity-action-modals')
</section>
