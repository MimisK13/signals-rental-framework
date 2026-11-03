<?php

declare(strict_types=1);

namespace App\Domain\Documents\Presentation\Livewire;

use App\Domain\Documents\Actions\RestoreDocumentTemplateVersion;
use App\Domain\Documents\Actions\UpdateDocumentSequence;
use App\Domain\Documents\Actions\UpdateDocumentTemplate;
use App\Domain\Documents\Contracts\DocumentRenderer;
use App\Domain\Documents\Data\UpdateDocumentSequenceData;
use App\Domain\Documents\Data\UpdateDocumentTemplateData;
use App\Domain\Documents\Enums\DocumentOutputFormat;
use App\Domain\Documents\Models\DocumentSequence;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\DocumentTemplateVersion;
use App\Domain\Documents\Services\DocumentFieldSnippetBuilder;
use App\Domain\Documents\Services\DocumentResolverRegistry;
use App\Domain\Documents\Services\DocumentSampleData;
use App\Domain\Documents\Services\PageDimensions;
use App\Domain\Documents\Services\PdfPageLayout;
use App\Domain\Documents\Services\TemplateLineDiff;
use App\Domain\Invoices\Models\CreditNote;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Models\InvoicePayment;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberStore;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.editor')]
#[Title('Edit Document Template')]
final class Editor extends Component
{
    use WithFileUploads;

    public DocumentTemplate $template;

    public string $bodyHtml = '';

    public string $headerHtml = '';

    public string $footerHtml = '';

    public string $stylesCss = '';

    public string $activeTab = 'body';

    public string $fieldSearch = '';

    public string $previewHtml = '';

    public string $previewError = '';

    public string $previewPrompt = '';

    public string $previewMode = 'sample';

    public ?int $sourceRecordId = null;

    public int $previewZoom = 100;

    /** @var array<string, mixed> */
    public array $pageConfig = [];

    /** @var array{top: float, right: float, bottom: float, left: float} */
    public array $pageDisplayMargins = [
        'top' => 10,
        'right' => 10,
        'bottom' => 10,
        'left' => 10,
    ];

    public float $pageDisplayHeaderHeight = 25;

    public float $pageDisplayFooterHeight = 15;

    public float $pageDisplayWidth = 210;

    public float $pageDisplayHeight = 297;

    /** @var array<string, mixed> */
    public array $brandingConfig = [];

    public string $sequencePrefix = '';

    public string $sequenceSuffix = '';

    public bool $sequenceIncludeYear = false;

    public bool $sequenceIncludeMonth = false;

    public int $sequenceNextNumber = 1;

    public int $sequencePadding = 4;

    public ?DocumentSequence $sequence = null;

    /** @var Collection<int, DocumentTemplateVersion> */
    public Collection $versions;

    public bool $showDiffPanel = false;

    public ?int $diffLeftVersionId = null;

    public ?int $diffRightVersionId = null;

    public string $diffLeftLabel = '';

    public string $diffRightLabel = '';

    /** @var list<array{type: string, lines: list<string>, count?: int}> */
    public array $diffOps = [];

    /** @var list<array{name: string, fields: list<array<string, mixed>>}> */
    public array $fieldTree = [];

    /** @var list<array{id: int, label: string}> */
    public array $sourceRecordOptions = [];

    public mixed $logoUpload = null;

    public function mount(DocumentTemplate|int|string $documentTemplate): void
    {
        Gate::authorize('viewAny', DocumentTemplate::class);
        $documentTemplate = $documentTemplate instanceof DocumentTemplate
            ? $documentTemplate
            : DocumentTemplate::query()->findOrFail((int) $documentTemplate);
        Gate::authorize('view', $documentTemplate);

        $this->template = $documentTemplate->load('store');
        $this->bodyHtml = (string) $documentTemplate->body_html;
        $this->headerHtml = (string) ($documentTemplate->header_html ?? '');
        $this->footerHtml = (string) ($documentTemplate->footer_html ?? '');
        $this->stylesCss = (string) ($documentTemplate->styles_css ?? '');
        $this->pageConfig = array_replace_recursive(
            config('documents.defaults.page', []),
            $documentTemplate->page_config ?? [],
        );
        $this->brandingConfig = $documentTemplate->branding_config ?? [];
        $this->syncPageDisplayFromConfig();

        $this->fieldTree = app(DocumentResolverRegistry::class)->fieldTree($documentTemplate->resolverType());
        $this->loadVersions();
        $this->loadSequence();
        $this->loadSourceRecordOptions();
        $this->refreshPreview();
    }

    public function loadVersions(): void
    {
        $this->versions = $this->template->versions()->limit(20)->get();
    }

    public function loadSequence(): void
    {
        $this->sequence = DocumentSequence::query()
            ->where('store_id', $this->template->store_id)
            ->where('document_type', $this->template->document_type)
            ->first();

        if ($this->sequence === null) {
            return;
        }

        $this->sequencePrefix = (string) ($this->sequence->prefix ?? '');
        $this->sequenceSuffix = (string) ($this->sequence->suffix ?? '');
        $this->sequenceIncludeYear = (bool) $this->sequence->include_year;
        $this->sequenceIncludeMonth = (bool) $this->sequence->include_month;
        $this->sequenceNextNumber = (int) $this->sequence->next_number;
        $this->sequencePadding = (int) $this->sequence->padding;
    }

    public function loadSourceRecordOptions(): void
    {
        if (! $this->userCanAccessTemplateStore()) {
            $this->sourceRecordOptions = [];

            return;
        }

        $storeId = $this->template->store_id;

        $this->sourceRecordOptions = match ($this->template->resolverType()) {
            'opportunity', 'return_list' => Opportunity::query()
                ->where('store_id', $storeId)
                ->orderByDesc('updated_at')
                ->limit(25)
                ->get(['id', 'number', 'subject'])
                ->map(fn (Opportunity $opportunity): array => [
                    'id' => $opportunity->id,
                    'label' => trim($opportunity->number.' · '.$opportunity->subject),
                ])
                ->all(),
            'invoice' => Invoice::query()
                ->where('store_id', $storeId)
                ->orderByDesc('updated_at')
                ->limit(25)
                ->get(['id', 'number'])
                ->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'label' => (string) $invoice->number,
                ])
                ->all(),
            'credit_note' => CreditNote::query()
                ->whereHas('invoice', fn ($query) => $query->where('store_id', $storeId))
                ->orderByDesc('updated_at')
                ->limit(25)
                ->get(['id', 'number'])
                ->map(fn (CreditNote $creditNote): array => [
                    'id' => $creditNote->id,
                    'label' => (string) $creditNote->number,
                ])
                ->all(),
            'receipt' => InvoicePayment::query()
                ->whereHas('invoice', fn ($query) => $query->where('store_id', $storeId))
                ->with('invoice:id,number')
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->limit(25)
                ->get(['id', 'reference', 'invoice_id'])
                ->map(function (InvoicePayment $payment): array {
                    $label = trim((string) ($payment->reference ?? ''));

                    if ($label === '' && $payment->invoice !== null) {
                        $label = (string) $payment->invoice->number;
                    }

                    if ($label === '') {
                        $label = 'Payment #'.$payment->id;
                    }

                    return [
                        'id' => $payment->id,
                        'label' => $label,
                    ];
                })
                ->all(),
            'statement' => Member::query()
                ->whereIn('id', MemberStore::query()
                    ->where('store_id', $storeId)
                    ->select('member_id'))
                ->orderByDesc('updated_at')
                ->limit(25)
                ->get(['id', 'name'])
                ->map(fn (Member $member): array => [
                    'id' => $member->id,
                    'label' => (string) $member->name,
                ])
                ->all(),
            'purchase_order' => PurchaseOrder::query()
                ->where('store_id', $storeId)
                ->orderByDesc('updated_at')
                ->limit(25)
                ->get(['id', 'number'])
                ->map(fn (PurchaseOrder $purchaseOrder): array => [
                    'id' => $purchaseOrder->id,
                    'label' => (string) $purchaseOrder->number,
                ])
                ->all(),
            default => [],
        };
    }

    public function sourceRecordPlaceholder(): string
    {
        return match ($this->template->resolverType()) {
            'invoice' => 'Select invoice…',
            'credit_note' => 'Select credit note…',
            'receipt' => 'Select payment…',
            'statement' => 'Select customer…',
            'purchase_order' => 'Select purchase order…',
            default => 'Select opportunity…',
        };
    }

    public function sourceRecordEmptyLabel(): string
    {
        return match ($this->template->resolverType()) {
            'invoice' => 'No invoices found for this store',
            'credit_note' => 'No credit notes found for this store',
            'receipt' => 'No payments found for this store',
            'statement' => 'No customers found for this store',
            'purchase_order' => 'No purchase orders found for this store',
            default => 'No opportunities found for this store',
        };
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['bodyHtml', 'headerHtml', 'footerHtml', 'stylesCss', 'previewMode', 'sourceRecordId', 'previewZoom'], true)
            || str_starts_with($property, 'pageConfig')) {
            $this->refreshPreview();
        }

        if (str_starts_with($property, 'pageDisplay')) {
            $this->syncPageConfigFromDisplay();
            $this->refreshPreview();
        }
    }

    public function adjustPreviewZoom(int $delta): void
    {
        $this->previewZoom = max(50, min(150, $this->previewZoom + $delta));
    }

    public function resetPreviewZoom(): void
    {
        $this->previewZoom = 100;
    }

    /**
     * Inline CSS for the page-shaped preview canvas.
     */
    public function previewPaperStyle(): string
    {
        $dimensions = PageDimensions::sizeMm($this->pageConfig);
        $zoom = max(0.5, min(1.5, $this->previewZoom / 100));

        return "width: {$dimensions['width']}mm; min-height: {$dimensions['height']}mm; zoom: {$zoom};";
    }

    /**
     * Page dimensions for preview chrome (no zoom — used by new-window wrapper).
     */
    public function previewPaperDimensionsStyle(): string
    {
        $dimensions = PageDimensions::sizeMm($this->pageConfig);

        return "width: {$dimensions['width']}mm; min-height: {$dimensions['height']}mm;";
    }

    public function previewStatusLabel(): string
    {
        if ($this->previewError !== '') {
            return 'Preview error';
        }

        if ($this->previewPrompt !== '') {
            return 'Awaiting record selection';
        }

        if ($this->previewHtml === '') {
            return 'Rendering…';
        }

        return $this->previewMode === 'real' ? 'Preview synced · real data' : 'Preview synced · sample data';
    }

    public function previewPromptMessage(): string
    {
        return match ($this->template->resolverType()) {
            'invoice' => 'Select an invoice to preview real data.',
            'credit_note' => 'Select a credit note to preview real data.',
            'receipt' => 'Select a payment to preview real data.',
            'statement' => 'Select a customer to preview real data.',
            'purchase_order' => 'Select a purchase order to preview real data.',
            default => 'Select an opportunity to preview real data.',
        };
    }

    public function previewPageLabel(): string
    {
        $size = (string) ($this->pageConfig['page_size'] ?? 'A4');
        $orientation = ucfirst((string) ($this->pageConfig['page_orientation'] ?? 'portrait'));

        return "{$size} · {$orientation}";
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['body', 'header', 'footer', 'styles'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function insertField(string $fieldName, string $type): void
    {
        $snippet = app(DocumentFieldSnippetBuilder::class)->insertSnippet($fieldName, $type);
        $this->insertIntoActiveTab($snippet);
    }

    public function insertSnippet(string $snippet): void
    {
        $this->insertIntoActiveTab($snippet);
    }

    public function insertLineItemRowSnippet(): void
    {
        $this->insertSnippet("@foreach (\$items as \$item)\n    <tr><td>{{ \$item->name }}</td></tr>\n@endforeach");
    }

    public function save(): void
    {
        (new UpdateDocumentTemplate)($this->template, new UpdateDocumentTemplateData(
            body_html: $this->bodyHtml,
            header_html: $this->headerHtml,
            footer_html: $this->footerHtml,
            styles_css: $this->stylesCss,
            change_note: 'Saved from template editor',
        ));

        $this->template->refresh();
        $this->loadVersions();
        $this->refreshPreview();
        $this->dispatch('document-template-saved');
    }

    public function saveSettings(): void
    {
        Gate::authorize('update', $this->template);

        $this->syncPageConfigFromDisplay();

        (new UpdateDocumentTemplate)($this->template, new UpdateDocumentTemplateData(
            page_config: $this->pageConfig,
            branding_config: $this->brandingConfig,
            change_note: 'Updated page and branding settings',
        ));

        if ($this->sequence !== null) {
            Gate::authorize('update', $this->sequence);

            (new UpdateDocumentSequence)($this->sequence, new UpdateDocumentSequenceData(
                prefix: $this->sequencePrefix,
                suffix: $this->sequenceSuffix !== '' ? $this->sequenceSuffix : null,
                include_year: $this->sequenceIncludeYear,
                include_month: $this->sequenceIncludeMonth,
                next_number: $this->sequenceNextNumber,
                padding: $this->sequencePadding,
            ));

            $this->loadSequence();
        }

        $this->template->refresh();
        $this->refreshPreview();
        $this->dispatch('close-modal', 'document-template-page-setup');
        $this->js("\$dispatch('close-modal', 'document-template-page-setup')");
        $this->dispatch('document-template-settings-saved');
    }

    public function updatedPageConfigDisplayUnit(): void
    {
        $this->syncPageDisplayFromConfig();
    }

    public function updatedPageConfigPageSize(): void
    {
        if (strtoupper((string) ($this->pageConfig['page_size'] ?? '')) === 'CUSTOM') {
            $this->pageConfig['page_width_mm'] ??= 210;
            $this->pageConfig['page_height_mm'] ??= 297;
            $this->syncPageDisplayFromConfig();
        }
    }

    /**
     * @return array<string, string>
     */
    public function fontFamilyOptions(): array
    {
        return [
            '' => 'Default (inherit store branding)',
            'Helvetica, Arial, sans-serif' => 'Sans-serif',
            'DejaVu Sans, sans-serif' => 'DejaVu Sans',
            'Times, Times New Roman, serif' => 'Serif',
            'Georgia, serif' => 'Georgia',
            'DejaVu Sans Mono, Courier, monospace' => 'Monospace',
        ];
    }

    public function isKnownFontFamily(): bool
    {
        $stored = (string) ($this->brandingConfig['font_family'] ?? '');

        return $stored === '' || array_key_exists($stored, $this->fontFamilyOptions());
    }

    public function pageSetupUnitLabel(): string
    {
        return strtoupper(PageDimensions::displayUnit($this->pageConfig));
    }

    public function showDiff(int $versionId): void
    {
        Gate::authorize('update', $this->template);

        $version = $this->findOwnedVersion($versionId);
        $this->template->refresh();

        $this->diffLeftLabel = 'Version '.$version->version;
        $this->diffRightLabel = 'Saved version (v'.$this->template->version.')';
        $this->diffLeftVersionId = $version->id;
        $this->diffRightVersionId = null;
        $this->diffOps = app(TemplateLineDiff::class)->diff(
            (string) $version->body_html,
            (string) $this->template->body_html,
        );
        $this->showDiffPanel = true;
    }

    public function showDiffBetween(int $leftVersionId, int $rightVersionId): void
    {
        Gate::authorize('update', $this->template);

        $left = $this->findOwnedVersion($leftVersionId);
        $right = $this->findOwnedVersion($rightVersionId);

        $this->diffLeftLabel = 'Version '.$left->version;
        $this->diffRightLabel = 'Version '.$right->version;
        $this->diffLeftVersionId = $left->id;
        $this->diffRightVersionId = $right->id;
        $this->diffOps = app(TemplateLineDiff::class)->diff(
            (string) $left->body_html,
            (string) $right->body_html,
        );
        $this->showDiffPanel = true;
    }

    public function closeDiff(): void
    {
        $this->showDiffPanel = false;
        $this->diffOps = [];
        $this->diffLeftLabel = '';
        $this->diffRightLabel = '';
        $this->diffLeftVersionId = null;
        $this->diffRightVersionId = null;
    }

    public function restoreVersion(int $versionId): void
    {
        $version = DocumentTemplateVersion::query()->findOrFail($versionId);
        (new RestoreDocumentTemplateVersion)($this->template, $version);

        $this->template->refresh();
        $this->bodyHtml = (string) $this->template->body_html;
        $this->headerHtml = (string) ($this->template->header_html ?? '');
        $this->footerHtml = (string) ($this->template->footer_html ?? '');
        $this->stylesCss = (string) ($this->template->styles_css ?? '');
        $this->pageConfig = array_replace_recursive(
            config('documents.defaults.page', []),
            $this->template->page_config ?? [],
        );
        $this->syncPageDisplayFromConfig();
        $this->loadVersions();
        $this->refreshPreview();
        $this->dispatchEditorSync();
        $this->dispatch('document-template-version-restored');
    }

    public function updatedLogoUpload(): void
    {
        $this->validate([
            'logoUpload' => ['required', 'image', 'max:2048', 'mimes:png,jpg,jpeg,svg,webp'],
        ]);

        $path = $this->logoUpload->store('document-templates/'.$this->template->id, 'public');

        if ($path === false) {
            $this->addError('logoUpload', 'Failed to upload logo.');

            return;
        }

        $this->brandingConfig['logo_path'] = $path;
        $this->logoUpload = null;
    }

    public function refreshPreview(): void
    {
        $this->previewError = '';
        $this->previewPrompt = '';
        $this->previewHtml = '';

        if ($this->previewMode === 'real' && $this->sourceRecordId === null) {
            $this->previewPrompt = $this->previewPromptMessage();

            return;
        }

        try {
            $renderedData = $this->resolvePreviewData();

            $result = app(DocumentRenderer::class)->renderFromSnapshot(
                $this->previewTemplate(),
                $renderedData,
                DocumentOutputFormat::Preview,
            );

            if (! $result->success) {
                $this->previewError = $result->error ?? 'Unable to render preview.';

                return;
            }

            $this->previewHtml = $result->html ?? '';
        } catch (ValidationException $exception) {
            $this->previewError = collect($exception->errors())->flatten()->first()
                ?? 'Unable to render preview.';
        }
    }

    /**
     * @return list<array{name: string, fields: list<array<string, mixed>>}>
     */
    public function filteredFieldTree(): array
    {
        $needle = strtolower(trim($this->fieldSearch));

        if ($needle === '') {
            return $this->fieldTree;
        }

        $filtered = [];

        foreach ($this->fieldTree as $group) {
            $fields = array_values(array_filter(
                $group['fields'],
                fn (array $field): bool => str_contains(strtolower((string) ($field['label'] ?? $field['name'])), $needle)
                    || str_contains(strtolower((string) $field['name']), $needle),
            ));

            if ($fields !== []) {
                $filtered[] = [
                    'name' => $group['name'],
                    'fields' => $fields,
                ];
            }
        }

        return $filtered;
    }

    private function syncPageDisplayFromConfig(): void
    {
        $unit = PageDimensions::displayUnit($this->pageConfig);
        $margins = PdfPageLayout::baseMargins($this->pageConfig);

        $this->pageDisplayMargins = [
            'top' => PageDimensions::mmToDisplay($margins['top'], $unit),
            'right' => PageDimensions::mmToDisplay($margins['right'], $unit),
            'bottom' => PageDimensions::mmToDisplay($margins['bottom'], $unit),
            'left' => PageDimensions::mmToDisplay($margins['left'], $unit),
        ];

        $this->pageDisplayHeaderHeight = PageDimensions::mmToDisplay(
            (float) ($this->pageConfig['header_height_mm'] ?? config('documents.defaults.page.header_height_mm', 25)),
            $unit,
        );

        $this->pageDisplayFooterHeight = PageDimensions::mmToDisplay(
            (float) ($this->pageConfig['footer_height_mm'] ?? config('documents.defaults.page.footer_height_mm', 15)),
            $unit,
        );

        $this->pageDisplayWidth = PageDimensions::mmToDisplay(
            (float) ($this->pageConfig['page_width_mm'] ?? 210),
            $unit,
        );

        $this->pageDisplayHeight = PageDimensions::mmToDisplay(
            (float) ($this->pageConfig['page_height_mm'] ?? 297),
            $unit,
        );
    }

    private function syncPageConfigFromDisplay(): void
    {
        $unit = PageDimensions::displayUnit($this->pageConfig);

        $this->pageConfig['display_unit'] = $unit;
        $this->pageConfig['margins'] = [
            'top' => PageDimensions::displayToMm($this->pageDisplayMargins['top'], $unit),
            'right' => PageDimensions::displayToMm($this->pageDisplayMargins['right'], $unit),
            'bottom' => PageDimensions::displayToMm($this->pageDisplayMargins['bottom'], $unit),
            'left' => PageDimensions::displayToMm($this->pageDisplayMargins['left'], $unit),
        ];
        $this->pageConfig['header_height_mm'] = PageDimensions::displayToMm($this->pageDisplayHeaderHeight, $unit);
        $this->pageConfig['footer_height_mm'] = PageDimensions::displayToMm($this->pageDisplayFooterHeight, $unit);

        if (strtoupper((string) ($this->pageConfig['page_size'] ?? '')) === 'CUSTOM') {
            $this->pageConfig['page_width_mm'] = PageDimensions::displayToMm($this->pageDisplayWidth, $unit);
            $this->pageConfig['page_height_mm'] = PageDimensions::displayToMm($this->pageDisplayHeight, $unit);
        }
    }

    private function findOwnedVersion(int $versionId): DocumentTemplateVersion
    {
        $version = DocumentTemplateVersion::query()->findOrFail($versionId);

        if ($version->document_template_id !== $this->template->id) {
            abort(404);
        }

        return $version;
    }

    private function insertIntoActiveTab(string $snippet): void
    {
        match ($this->activeTab) {
            'header' => $this->headerHtml = rtrim($this->headerHtml)."\n".$snippet,
            'footer' => $this->footerHtml = rtrim($this->footerHtml)."\n".$snippet,
            'styles' => $this->stylesCss = rtrim($this->stylesCss)."\n".$snippet,
            default => $this->bodyHtml = rtrim($this->bodyHtml)."\n".$snippet,
        };

        $this->refreshPreview();
        $this->dispatchEditorSync();
    }

    private function dispatchEditorSync(): void
    {
        $this->dispatch('document-template-editor-sync');
    }

    private function previewTemplate(): DocumentTemplate
    {
        $preview = $this->template->replicate();
        $preview->id = $this->template->id;
        $preview->version = $this->template->version;
        $preview->setRelation('store', $this->template->store);
        $preview->fill([
            'body_html' => $this->bodyHtml,
            'header_html' => $this->headerHtml,
            'footer_html' => $this->footerHtml,
            'styles_css' => $this->stylesCss,
            'page_config' => $this->pageConfig,
            'branding_config' => $this->brandingConfig,
        ]);

        return $preview;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePreviewData(): array
    {
        if ($this->previewMode !== 'real') {
            return DocumentSampleData::forResolver($this->template->resolverType());
        }

        $resolverType = $this->template->resolverType();
        $recordId = $this->assertRealPreviewRecordAccess($resolverType);

        return app(DocumentResolverRegistry::class)->resolve($resolverType, $recordId);
    }

    private function assertRealPreviewRecordAccess(string $resolverType): int
    {
        $storeId = $this->template->store_id;
        $recordId = (int) $this->sourceRecordId;

        return match ($resolverType) {
            'opportunity', 'return_list' => $this->assertOpportunityPreviewAccess($recordId, $storeId),
            'invoice' => $this->assertInvoicePreviewAccess($recordId, $storeId),
            'credit_note' => $this->assertCreditNotePreviewAccess($recordId, $storeId),
            'receipt' => $this->assertReceiptPreviewAccess($recordId, $storeId),
            'statement' => $this->assertStatementPreviewAccess($recordId, $storeId),
            'purchase_order' => $this->assertPurchaseOrderPreviewAccess($recordId, $storeId),
            default => throw ValidationException::withMessages([
                'sourceRecordId' => ['Real-data preview is not available for this document type.'],
            ]),
        };
    }

    private function assertOpportunityPreviewAccess(int $recordId, int $storeId): int
    {
        $opportunity = Opportunity::query()->findOrFail($recordId);
        Gate::authorize('view', $opportunity);

        if ($opportunity->store_id !== $storeId) {
            throw ValidationException::withMessages([
                'sourceRecordId' => ['The selected opportunity does not belong to this template store.'],
            ]);
        }

        return $opportunity->id;
    }

    private function assertInvoicePreviewAccess(int $recordId, int $storeId): int
    {
        $invoice = Invoice::query()->findOrFail($recordId);
        Gate::authorize('view', $invoice);

        if ($invoice->store_id !== $storeId) {
            throw ValidationException::withMessages([
                'sourceRecordId' => ['The selected invoice does not belong to this template store.'],
            ]);
        }

        return $invoice->id;
    }

    private function assertCreditNotePreviewAccess(int $recordId, int $storeId): int
    {
        $creditNote = CreditNote::query()->with('invoice')->findOrFail($recordId);
        Gate::authorize('view', $creditNote->invoice);

        if ($creditNote->invoice?->store_id !== $storeId) {
            throw ValidationException::withMessages([
                'sourceRecordId' => ['The selected credit note does not belong to this template store.'],
            ]);
        }

        return $creditNote->id;
    }

    private function assertReceiptPreviewAccess(int $recordId, int $storeId): int
    {
        $payment = InvoicePayment::query()->with('invoice')->findOrFail($recordId);
        Gate::authorize('view', $payment->invoice);

        if ($payment->invoice?->store_id !== $storeId) {
            throw ValidationException::withMessages([
                'sourceRecordId' => ['The selected payment does not belong to this template store.'],
            ]);
        }

        return $payment->id;
    }

    private function assertStatementPreviewAccess(int $recordId, int $storeId): int
    {
        $member = Member::query()->findOrFail($recordId);
        Gate::authorize('view', $member);

        $hasStoreAccess = MemberStore::query()
            ->where('member_id', $member->id)
            ->where('store_id', $storeId)
            ->exists()
            || Invoice::query()
                ->where('member_id', $member->id)
                ->where('store_id', $storeId)
                ->exists();

        if (! $hasStoreAccess) {
            throw ValidationException::withMessages([
                'sourceRecordId' => ['The selected customer is not linked to this template store.'],
            ]);
        }

        return $member->id;
    }

    private function assertPurchaseOrderPreviewAccess(int $recordId, int $storeId): int
    {
        $purchaseOrder = PurchaseOrder::query()->findOrFail($recordId);
        Gate::authorize('view', $purchaseOrder);

        if ($purchaseOrder->store_id !== $storeId) {
            throw ValidationException::withMessages([
                'sourceRecordId' => ['The selected purchase order does not belong to this template store.'],
            ]);
        }

        return $purchaseOrder->id;
    }

    private function userCanAccessTemplateStore(): bool
    {
        $storeIds = auth()->user()?->accessibleStoreIds();

        if ($storeIds !== null && ! in_array($this->template->store_id, $storeIds, true)) {
            return false;
        }

        return true;
    }

    public function render(): View
    {
        return view('documents::templates.edit');
    }
}
