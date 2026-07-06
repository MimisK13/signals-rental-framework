<?php

use App\Actions\Documents\RestoreDocumentTemplateVersion;
use App\Actions\Documents\UpdateDocumentSequence;
use App\Actions\Documents\UpdateDocumentTemplate;
use App\Contracts\Documents\DocumentRenderer;
use App\Data\Documents\UpdateDocumentSequenceData;
use App\Data\Documents\UpdateDocumentTemplateData;
use App\Enums\DocumentOutputFormat;
use App\Models\CreditNote;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\PurchaseOrder;
use App\Services\Documents\DocumentFieldSnippetBuilder;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\DocumentSampleData;
use App\Services\Documents\PageDimensions;
use App\Services\Documents\PdfPageLayout;
use App\Services\Documents\TemplateLineDiff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.editor')] #[Title('Edit Document Template')] class extends Component {
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

    public $logoUpload = null;

    public function mount(DocumentTemplate $documentTemplate): void
    {
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

    public function updated($property): void
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
}; ?>

<div class="dt-editor"
     data-testid="document-template-editor"
     x-data="{
         sidebarCollapsed: (() => {
             try {
                 const stored = localStorage.getItem('dt-editor-sidebar-collapsed');
                 if (stored === null || stored === '') {
                     return false;
                 }
                 return stored === '1';
             } catch {
                 return false;
             }
         })(),
         versionsOpen: false,
         previewWidth: Math.min(70, Math.max(25, parseFloat(localStorage.getItem('dt-editor-preview-width') || '44') || 44)),
         dragging: false,
         toggleSidebar() {
             this.sidebarCollapsed = !this.sidebarCollapsed;
             localStorage.setItem('dt-editor-sidebar-collapsed', this.sidebarCollapsed ? '1' : '0');
         },
         toggleVersions() {
             this.versionsOpen = !this.versionsOpen;
         },
         openVersions() {
             if (this.versionsOpen) {
                 return;
             }
             this.versionsOpen = true;
         },
         startResize(event) {
             this.dragging = true;
             this._resizeStartX = event.clientX;
             this._resizeStartWidth = this.previewWidth;
             this._resizeMainEl = this.$refs.mainLayout;
             document.body.style.userSelect = 'none';
             document.body.style.cursor = 'col-resize';
         },
         onResize(event) {
             if (!this.dragging || !this._resizeMainEl) { return; }
             const rect = this._resizeMainEl.getBoundingClientRect();
             const handleW = 5;
             const sidebarEl = this._resizeMainEl.querySelector('.dt-sidebar');
             const sidebarW = sidebarEl ? sidebarEl.getBoundingClientRect().width : 272;
             const mainW = rect.width - sidebarW - handleW;
             const deltaPct = ((this._resizeStartX - event.clientX) / mainW) * 100;
             let next = this._resizeStartWidth + deltaPct;
             const previewPx = (next / 100) * mainW;
             const codePx = mainW - previewPx;
             if (previewPx < 300) { next = (300 / mainW) * 100; }
             if (codePx < 360) { next = ((mainW - 360) / mainW) * 100; }
             this.previewWidth = Math.min(70, Math.max(25, next));
         },
         endResize() {
             if (!this.dragging) { return; }
             this.dragging = false;
             document.body.style.userSelect = '';
             document.body.style.cursor = '';
             localStorage.setItem('dt-editor-preview-width', String(Math.round(this.previewWidth * 10) / 10));
         },
         openInNewWindow() {
             const panel = this.$refs.previewPanel;
             const html = panel?.dataset.previewHtml;
             if (!html) { return; }
             let paperStyle = panel?.dataset.paperStyle || '';
             paperStyle = paperStyle.replace(/zoom\s*:\s*[^;]+;?/gi, '').trim();
             const w = window.open('', '_blank');
             if (!w) { return; }
             const doc = w.document;
             doc.title = 'Document preview';
             const style = doc.createElement('style');
             style.textContent = '*{box-sizing:border-box;margin:0}body{min-height:100vh;background:#e8e8ea;display:flex;align-items:flex-start;justify-content:center;padding:32px 16px}.dt-paper{background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.08),0 16px 48px rgba(0,0,0,.06)}iframe{width:100%;height:100%;min-height:inherit;border:0;background:#fff;display:block}';
             doc.head.appendChild(style);
             const paper = doc.createElement('div');
             paper.className = 'dt-paper';
             paper.setAttribute('style', paperStyle);
             const iframe = doc.createElement('iframe');
             iframe.title = 'Document preview';
             iframe.setAttribute('sandbox', '');
             iframe.srcdoc = html;
             paper.appendChild(iframe);
             doc.body.appendChild(paper);
         },
     }"
     @pointermove.window="onResize($event)"
     @pointerup.window="endResize()"
     @pointercancel.window="endResize()"
     x-init="$watch('$wire.showDiffPanel', (open) => { if (open) { openVersions(); } })">
<style>
    .dt-editor { display: flex; flex-direction: column; height: 100vh; background: var(--content-bg); color: var(--text-primary); --dt-topbar-h: 52px; --dt-row-h: 32px; --dt-sidebar-w: 272px; --dt-sidebar-rail: 36px; }
    .dt-topbar { height: var(--dt-topbar-h); display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 16px; border-bottom: 1px solid var(--card-border); background: var(--navy); color: #fff; }
    .dt-topbar h1 { font-family: var(--font-display); font-size: 14px; font-weight: 600; margin: 0; color: #fff; }
    .dt-topbar-meta { font-family: var(--font-mono); font-size: 11px; color: rgba(255,255,255,0.65); }
    .dt-doc-badge { font-family: var(--font-display); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; padding: 3px 10px; background: rgba(5,150,105,0.18); color: var(--green); }
    .dt-version { font-family: var(--font-mono); font-size: 10px; color: rgba(255,255,255,0.55); }
    .dt-main { display: flex; min-height: 0; flex: 1; height: calc(100vh - var(--dt-topbar-h)); }
    .dt-sidebar { width: var(--dt-sidebar-w); min-width: var(--dt-sidebar-w); border-right: 1px solid var(--card-border); background: var(--card-bg); display: flex; flex-direction: column; transition: width 0.2s ease, min-width 0.2s ease; overflow: hidden; flex-shrink: 0; }
    .dt-sidebar--collapsed { width: var(--dt-sidebar-rail); min-width: var(--dt-sidebar-rail); }
    .dt-sidebar-header { padding: 12px 14px; border-bottom: 1px solid var(--card-border); font-family: var(--font-mono); font-size: 9px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-shrink: 0; min-height: var(--dt-row-h); }
    .dt-sidebar--collapsed .dt-sidebar-header { display: none; }
    .dt-sidebar-body { display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; }
    .dt-sidebar--collapsed .dt-sidebar-body { display: none; }
    .dt-sidebar-rail-expand { display: none; flex: 1; align-items: flex-start; justify-content: center; padding-top: 10px; width: 100%; border: none; background: transparent; cursor: pointer; color: var(--text-muted); }
    .dt-sidebar--collapsed .dt-sidebar-rail-expand { display: flex; }
    .dt-sidebar-rail-expand:hover { color: var(--text-primary); background: rgba(0,0,0,0.04); }
    .dark .dt-sidebar-rail-expand:hover { background: rgba(255,255,255,0.06); }
    .dt-sidebar-rail-expand svg { width: 14px; height: 14px; }
    .dt-sidebar-header-label { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dt-sidebar-toggle { display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; padding: 0; border: none; background: transparent; color: var(--text-muted); cursor: pointer; border-radius: 3px; flex-shrink: 0; }
    .dt-sidebar-toggle:hover { background: rgba(0,0,0,0.06); color: var(--text-primary); }
    .dark .dt-sidebar-toggle:hover { background: rgba(255,255,255,0.08); }
    .dt-sidebar-toggle svg { width: 14px; height: 14px; }
    .dt-preview-header { padding: 12px 14px; border-bottom: 1px solid var(--card-border); font-family: var(--font-mono); font-size: 9px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); }
    .dt-field-search { padding: 6px 12px; border-bottom: 1px solid var(--grey-border, var(--card-border)); }
    .dt-field-search-input { width: 100%; padding: 5px 8px; border: 1px solid var(--card-border); border-radius: 3px; background: var(--base); color: var(--text-primary); font-family: var(--font-mono); font-size: 11px; line-height: 1.4; }
    .dt-field-search-input::placeholder { color: var(--text-muted); }
    .dt-field-search-input:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 1px rgba(5,150,105,0.25); }
    .dt-field-groups { overflow: auto; flex: 1; padding: 4px 0; }
    .dt-field-group { margin-bottom: 1px; }
    .dt-field-group-header { display: flex; align-items: center; gap: 6px; padding: 7px 14px; cursor: pointer; user-select: none; }
    .dt-field-group-header:hover { background: rgba(0,0,0,0.04); }
    .dark .dt-field-group-header:hover { background: rgba(255,255,255,0.06); }
    .dt-field-group-chevron { width: 14px; height: 14px; color: var(--text-muted); transition: transform 0.15s; flex-shrink: 0; }
    .dt-field-group.open .dt-field-group-chevron { transform: rotate(90deg); }
    .dt-field-group-title { font-family: var(--font-display); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); }
    .dt-field-group-count { font-family: var(--font-mono); font-size: 9px; color: var(--text-muted); margin-left: auto; background: var(--base); padding: 1px 6px; }
    .dt-field-group-items { display: none; padding-bottom: 4px; }
    .dt-field-group.open .dt-field-group-items { display: block; }
    .dt-field-btn { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 14px 5px 36px; border: none; background: transparent; color: var(--text-primary); font-family: var(--font-mono); font-size: 10px; cursor: pointer; position: relative; }
    .dt-field-btn:hover { background: var(--green-muted, rgba(5,150,105,0.12)); }
    .dt-field-btn-label { flex: 1; min-width: 0; }
    .dt-field-btn-insert { opacity: 0; font-family: var(--font-display); font-size: 9px; color: var(--green); font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
    .dt-field-btn:hover .dt-field-btn-insert { opacity: 1; }
    .dt-type-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .dt-type-string { background: var(--blue); }
    .dt-type-currency { background: var(--green); }
    .dt-type-date { background: var(--amber); }
    .dt-type-collection { background: var(--violet); }
    .dt-type-boolean { background: var(--red); }
    .dt-type-number, .dt-type-decimal, .dt-type-integer { background: var(--sky); }
    .dt-type-text, .dt-type-enum { background: var(--text-muted); }
    .dt-center { flex: 1; min-width: 360px; display: flex; flex-direction: column; border-right: none; }
    .dt-resize-handle { width: 5px; flex-shrink: 0; cursor: col-resize; background: transparent; position: relative; z-index: 2; touch-action: none; }
    .dt-resize-handle::after { content: ''; position: absolute; inset: 0 -2px; background: transparent; transition: background 0.15s; }
    .dt-resize-handle:hover::after, .dt-resize-handle--active::after { background: rgba(5,150,105,0.35); }
    .dt-resize-handle--active::after { background: rgba(5,150,105,0.5); }
    .dt-tabs { height: var(--dt-row-h); display: flex; align-items: stretch; gap: 2px; padding: 0 4px; border-bottom: 1px solid var(--card-border); background: var(--card-bg); flex-shrink: 0; }
    .dt-tab { display: flex; align-items: center; gap: 5px; padding: 0 12px; font-family: var(--font-display); font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; border: none; background: transparent; cursor: pointer; color: var(--text-muted); border-bottom: 2px solid transparent; margin-bottom: -1px; }
    .dt-tab:hover { color: var(--text-secondary); }
    .dt-tab.active { color: var(--text-primary); border-bottom-color: var(--green); }
    .dt-tab svg { width: 12px; height: 12px; opacity: 0.5; flex-shrink: 0; }
    .dt-tab.active svg { opacity: 0.85; }
    .dt-code { flex: 1; min-height: 0; padding: 12px; display: flex; flex-direction: column; background: var(--base); }
    .dt-cm-host { flex: 1; min-height: 420px; border: 1px solid var(--card-border); border-radius: 4px; overflow: hidden; }
    .dt-cm-host .cm-editor { height: 100%; }
    .dt-cm-fallback { flex: 1; min-height: 0; display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 2px dashed var(--amber, #d97706); border-radius: 6px; background: color-mix(in srgb, var(--amber, #d97706) 8%, var(--base)); }
    .dt-cm-fallback-hint { margin: 0; font-size: 12px; font-weight: 600; color: var(--amber, #b45309); }
    .dark .dt-cm-fallback { background: color-mix(in srgb, var(--amber, #f59e0b) 12%, var(--base)); border-color: var(--amber, #f59e0b); }
    .dark .dt-cm-fallback-hint { color: var(--amber, #fbbf24); }
    .dt-code textarea { width: 100%; height: 100%; min-height: 420px; resize: none; font-family: var(--font-mono); font-size: 12px; line-height: 1.5; padding: 12px; border: 1px solid var(--card-border); background: var(--base); color: var(--text-primary); }
    .dt-statusbar { height: var(--dt-row-h); background: var(--card-bg); border-top: 1px solid var(--card-border); display: flex; align-items: center; padding: 0 14px; gap: 16px; font-family: var(--font-mono); font-size: 10px; color: var(--text-muted); flex-shrink: 0; }
    .dt-statusbar-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green); flex-shrink: 0; }
    .dt-statusbar-dot.error { background: var(--red); }
    .dt-statusbar-right { margin-left: auto; display: flex; gap: 16px; }
    .dt-preview { min-width: 300px; display: flex; flex-direction: column; background: var(--content-bg); flex-shrink: 0; border-left: 1px solid var(--card-border); }
    .dt-preview-toolbar { height: var(--dt-row-h); display: flex; flex-wrap: nowrap; align-items: center; gap: 6px; padding: 0 10px; border-bottom: 1px solid var(--card-border); background: var(--card-bg); flex-shrink: 0; overflow-x: auto; }
    .dt-preview-toolbar-label { font-family: var(--font-mono); font-size: 9px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); flex-shrink: 0; white-space: nowrap; }
    .dt-preview-select { flex: 0 1 auto; min-width: 0; max-width: 140px; height: 24px; padding: 3px 8px; border: 1px solid var(--card-border); border-radius: 3px; background: var(--base); color: var(--text-primary); font-family: var(--font-mono); font-size: 11px; line-height: 1.2; cursor: pointer; appearance: auto; }
    .dt-preview-select:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 1px rgba(5,150,105,0.25); }
    .dt-toolbar-btn { display: inline-flex; align-items: center; justify-content: center; height: 24px; padding: 0 8px; border: 1px solid var(--card-border); border-radius: 3px; background: var(--base); color: var(--text-secondary); font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; cursor: pointer; white-space: nowrap; flex-shrink: 0; }
    .dt-toolbar-btn:hover:not(:disabled) { background: rgba(0,0,0,0.04); color: var(--text-primary); }
    .dark .dt-toolbar-btn:hover:not(:disabled) { background: rgba(255,255,255,0.06); }
    .dt-toolbar-btn:disabled { opacity: 0.45; cursor: not-allowed; }
    .dt-topbar .dt-topbar-ghost:hover { color: #fff !important; background: rgba(255,255,255,0.12) !important; }
    .dt-topbar-save { display: inline-flex; align-items: center; justify-content: center; height: 26px; padding: 0 11px; border: 1px solid rgba(5,150,105,0.55); border-radius: 3px; background: var(--green); color: #fff; font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; white-space: nowrap; flex-shrink: 0; line-height: 1; box-shadow: 0 1px 2px rgba(0,0,0,0.18); }
    .dt-topbar-save:hover:not(:disabled) { background: color-mix(in srgb, var(--green) 88%, #fff); border-color: color-mix(in srgb, var(--green) 88%, #fff); color: #fff; }
    .dt-topbar-save:disabled { opacity: 0.6; cursor: not-allowed; }
    .dt-preview-zoom { margin-left: auto; display: flex; align-items: center; gap: 3px; flex-shrink: 0; }
    .dt-zoom-btn { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--card-border); border-radius: 3px; background: var(--base); color: var(--text-muted); cursor: pointer; font-size: 12px; line-height: 1; font-family: var(--font-mono); flex-shrink: 0; }
    .dt-zoom-btn:hover { background: rgba(0,0,0,0.04); color: var(--text-primary); }
    .dark .dt-zoom-btn:hover { background: rgba(255,255,255,0.06); }
    .dt-zoom-level { font-family: var(--font-mono); font-size: 10px; color: var(--text-muted); min-width: 32px; text-align: center; flex-shrink: 0; }
    .dt-preview-frame-wrap { flex: 1; overflow: auto; padding: 32px 16px; background: var(--content-bg); }
    .dark .dt-preview-frame-wrap { background: radial-gradient(circle at 50% 50%, rgba(5,150,105,0.03), transparent 70%), repeating-conic-gradient(var(--navy) 0% 25%, var(--navy-mid) 0% 50%) 0 0 / 20px 20px; }
    .dt-preview-paper { margin: 0 auto; background: #fff; color: #111; box-shadow: 0 2px 8px rgba(0,0,0,0.08), 0 16px 48px rgba(0,0,0,0.06); border: 1px solid var(--card-border); }
    .dark .dt-preview-paper { box-shadow: 0 2px 8px rgba(0,0,0,0.4), 0 20px 60px rgba(0,0,0,0.3); border: none; }
    .dt-preview-error { margin: 12px; padding: 12px 14px; border: 1px solid rgba(239,68,68,0.35); border-radius: 4px; background: rgba(239,68,68,0.08); color: var(--red); font-size: 13px; line-height: 1.5; }
    .dt-preview-error-title { font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
    .dt-preview-prompt { margin: 12px; padding: 12px 14px; border: 1px solid var(--card-border); border-radius: 4px; background: var(--base); color: var(--text-muted); font-size: 13px; line-height: 1.5; }
    .dt-preview-loading { padding: 24px; text-align: center; font-family: var(--font-mono); font-size: 12px; color: var(--text-muted); }
    .dt-margin-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
    .dt-margin-wrap { display: flex; align-items: center; gap: 4px; }
    .dt-margin-wrap label { font-family: var(--font-mono); font-size: 9px; color: var(--text-muted); width: 10px; text-transform: uppercase; font-weight: 500; flex-shrink: 0; }
    .dt-setup-section + .dt-setup-section { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--card-border); }
    .dt-setup-heading { font-family: var(--font-display); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); margin: 0 0 8px; }
    .dt-versions { margin-top: 0; border-top: 1px solid var(--card-border); padding: 0; background: var(--card-bg); flex-shrink: 0; }
    .dt-versions-header { display: flex; align-items: center; gap: 8px; width: 100%; height: var(--dt-row-h); padding: 0 14px; border: none; border-bottom: 1px solid var(--card-border); background: transparent; font-family: var(--font-mono); font-size: 9px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); cursor: pointer; text-align: left; }
    .dt-versions-header:hover { background: rgba(0,0,0,0.03); color: var(--text-secondary); }
    .dark .dt-versions-header:hover { background: rgba(255,255,255,0.04); }
    .dt-versions-header-label { flex: 1; min-width: 0; }
    .dt-versions-count { font-family: var(--font-mono); font-size: 9px; color: var(--text-muted); background: var(--base); padding: 1px 6px; border-radius: 2px; flex-shrink: 0; }
    .dt-versions-chevron { width: 14px; height: 14px; flex-shrink: 0; transition: transform 0.2s ease; }
    .dt-versions--open .dt-versions-chevron { transform: rotate(180deg); }
    .dt-versions-body { max-height: 0; overflow: hidden; transition: max-height 0.25s ease; }
    .dt-versions--open .dt-versions-body { max-height: 240px; overflow: auto; }
    .dt-versions--open.dt-versions--has-diff .dt-versions-body { max-height: 520px; }
    .dt-version-row { display: flex; align-items: center; gap: 8px; height: var(--dt-row-h); max-height: var(--dt-row-h); padding: 0 14px; border-bottom: 1px solid var(--card-border); flex-wrap: nowrap; min-width: 0; overflow: hidden; }
    .dt-version-meta { flex: 1; min-width: 0; font-family: var(--font-mono); font-size: 10px; line-height: var(--dt-row-h); color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dt-version-meta strong { font-family: var(--font-display); font-weight: 600; }
    .dt-version-note { color: var(--text-muted); }
    .dt-versions-empty { padding: 8px 14px; font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); }
    .dt-diff-panel { margin-top: 12px; border: 1px solid var(--card-border); border-radius: 4px; background: var(--base); overflow: hidden; }
    .dt-diff-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; border-bottom: 1px solid var(--card-border); background: var(--card-bg); font-size: 12px; }
    .dt-diff-meta { color: var(--text-muted); font-size: 11px; }
    .dt-diff-body { max-height: 220px; overflow: auto; font-family: var(--font-mono); font-size: 11px; line-height: 1.45; }
    .dt-diff-line { display: flex; gap: 8px; padding: 1px 10px; white-space: pre-wrap; word-break: break-word; }
    .dt-diff-prefix { flex: 0 0 14px; user-select: none; color: var(--text-muted); }
    .dt-diff-equal { color: var(--text-secondary); }
    .dt-diff-insert { background: rgba(34, 197, 94, 0.12); color: var(--text-primary); }
    .dt-diff-delete { background: rgba(239, 68, 68, 0.12); color: var(--text-primary); }
    .dt-diff-collapsed { justify-content: center; padding: 6px 10px; color: var(--text-muted); font-style: italic; background: rgba(0, 0, 0, 0.03); }
    .dark .dt-diff-collapsed { background: rgba(255, 255, 255, 0.04); }
    .dt-version-actions { display: flex; align-items: center; gap: 4px; flex-shrink: 0; white-space: nowrap; }
    .dt-version-btn { display: inline-flex; align-items: center; justify-content: center; height: 22px; padding: 0 7px; border: 1px solid var(--card-border); border-radius: 3px; background: transparent; color: var(--text-secondary); font-family: var(--font-display); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; cursor: pointer; white-space: nowrap; }
    .dt-version-btn:hover { background: rgba(0,0,0,0.04); color: var(--text-primary); }
    .dark .dt-version-btn:hover { background: rgba(255,255,255,0.06); }
</style>

    <div class="dt-topbar">
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ route('admin.document-templates.index') }}" class="s-btn s-btn-ghost s-btn-sm text-white/80">Back</a>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1>{{ $template->name }}</h1>
                    <span class="dt-doc-badge">{{ str($template->document_type)->headline() }}</span>
                </div>
                <p class="dt-topbar-meta truncate">{{ $template->store?->name }} · v{{ $template->version }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="dt-version hidden sm:inline">v{{ $template->version }}</span>
            <button type="button" x-on:click="$dispatch('open-modal', 'document-template-page-setup')" class="s-btn s-btn-ghost s-btn-sm dt-topbar-ghost text-white/90">
                Page setup
            </button>
            <button type="button" wire:click="refreshPreview" wire:loading.attr="disabled" class="s-btn s-btn-ghost s-btn-sm dt-topbar-ghost text-white/90">
                <span wire:loading.remove wire:target="refreshPreview">Refresh preview</span>
                <span wire:loading wire:target="refreshPreview">Refreshing…</span>
            </button>
            @can('update', $template)
                <button type="button"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        class="dt-topbar-save"
                        data-testid="document-template-save">
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            @endcan
        </div>
    </div>

    <div class="dt-main" x-ref="mainLayout">
        <aside class="dt-sidebar"
               :class="{ 'dt-sidebar--collapsed': sidebarCollapsed }"
               data-testid="field-browser">
            <div class="dt-sidebar-header">
                <span class="dt-sidebar-header-label">Available fields</span>
                <button type="button"
                        class="dt-sidebar-toggle"
                        @click="toggleSidebar()"
                        :aria-expanded="!sidebarCollapsed"
                        aria-label="Toggle fields sidebar"
                        data-testid="sidebar-collapse-toggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
            </div>
            <button type="button"
                    class="dt-sidebar-rail-expand"
                    @click="toggleSidebar()"
                    aria-label="Expand fields sidebar"
                    data-testid="sidebar-expand-toggle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
            <div class="dt-sidebar-body">
            <div class="dt-field-search">
                <input type="search"
                       wire:model.live.debounce.300ms="fieldSearch"
                       placeholder="Search fields…"
                       class="dt-field-search-input"
                       aria-label="Search fields">
            </div>
            <div class="dt-field-groups">
                @forelse($this->filteredFieldTree() as $groupIndex => $group)
                    <div class="dt-field-group {{ $fieldSearch !== '' || $groupIndex < 3 ? 'open' : '' }}"
                         wire:key="field-group-{{ $group['name'] }}"
                         x-data
                         @click="if ($event.target.closest('.dt-field-group-header')) { $el.classList.toggle('open') }">
                        <div class="dt-field-group-header">
                            <svg class="dt-field-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                            <span class="dt-field-group-title">{{ $group['name'] }}</span>
                            <span class="dt-field-group-count">{{ count($group['fields']) }}</span>
                        </div>
                        <div class="dt-field-group-items">
                            @foreach($group['fields'] as $field)
                                @php($fieldType = (string) ($field['type'] ?? 'string'))
                                @php($fieldSnippet = app(DocumentFieldSnippetBuilder::class)->insertSnippet($field['name'], $fieldType))
                                <button type="button"
                                        wire:key="field-{{ $field['name'] }}"
                                        @click="$dispatch('dt-insert-snippet', { snippet: @js($fieldSnippet) })"
                                        class="dt-field-btn"
                                        title="{{ $field['label'] ?? $field['name'] }}">
                                    <span class="dt-type-dot dt-type-{{ $fieldType }}"></span>
                                    <span class="dt-field-btn-label">{{ $field['label'] ?? $field['name'] }}</span>
                                    <span class="dt-field-btn-insert">Insert</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-3 text-sm text-[var(--text-muted)]">No fields match your search.</p>
                @endforelse

                <div class="dt-field-group open" wire:key="field-group-snippets">
                    <div class="dt-field-group-header">
                        <svg class="dt-field-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        <span class="dt-field-group-title">Snippets</span>
                        <span class="dt-field-group-count">1</span>
                    </div>
                    <div class="dt-field-group-items">
                        @php($lineItemRowSnippet = "@foreach (\$items as \$item)\n    <tr><td>{{ \$item->name }}</td></tr>\n@endforeach")
                        <button type="button"
                                @click="$dispatch('dt-insert-snippet', { snippet: @js($lineItemRowSnippet) })"
                                class="dt-field-btn">
                            <span class="dt-type-dot dt-type-collection"></span>
                            <span class="dt-field-btn-label">Line item table row</span>
                            <span class="dt-field-btn-insert">Insert</span>
                        </button>
                    </div>
                </div>
            </div>
            </div>
        </aside>

        <section class="dt-center" data-testid="code-editor">
            <div class="dt-tabs">
                <button type="button" wire:click="setActiveTab('body')" class="dt-tab {{ $activeTab === 'body' ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
                    Body
                </button>
                <button type="button" wire:click="setActiveTab('header')" class="dt-tab {{ $activeTab === 'header' ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/></svg>
                    Header
                </button>
                <button type="button" wire:click="setActiveTab('footer')" class="dt-tab {{ $activeTab === 'footer' ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 15h18"/></svg>
                    Footer
                </button>
                <button type="button" wire:click="setActiveTab('styles')" class="dt-tab {{ $activeTab === 'styles' ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    Styles
                </button>
            </div>
            <div class="dt-code"
                 x-data="{
                     ...window.signals.documentTemplateEditor({
                         wire: $wire,
                         contents: {
                             body: @js($bodyHtml),
                             header: @js($headerHtml),
                             footer: @js($footerHtml),
                             styles: @js($stylesCss),
                         },
                     }),
                     activeTab: @entangle('activeTab').live,
                 }"
                 x-init="$nextTick(() => init())"
                 @dt-insert-snippet.window="insertAtCursor($event.detail.snippet)"
                 @destroy.window="destroy()">
                <div x-ref="cmHost"
                     data-testid="codemirror-host"
                     class="dt-cm-host"
                     x-bind:style="cmReady ? '' : 'position:absolute;opacity:0;width:0;height:0;overflow:hidden;pointer-events:none'"
                     wire:ignore></div>
                <div x-show="!cmReady"
                     x-cloak
                     class="dt-cm-fallback"
                     data-testid="codemirror-fallback">
                    @if($activeTab === 'body')
                        <textarea wire:model.live.debounce.500ms="bodyHtml" spellcheck="false" aria-label="Body editor"></textarea>
                    @elseif($activeTab === 'header')
                        <textarea wire:model.live.debounce.500ms="headerHtml" spellcheck="false" aria-label="Header editor"></textarea>
                    @elseif($activeTab === 'footer')
                        <textarea wire:model.live.debounce.500ms="footerHtml" spellcheck="false" aria-label="Footer editor"></textarea>
                    @else
                        <textarea wire:model.live.debounce.500ms="stylesCss" spellcheck="false" aria-label="Styles editor"></textarea>
                    @endif
                    <p class="dt-cm-fallback-hint" role="alert">CodeMirror unavailable — using plain textarea fallback.</p>
                </div>
            </div>
            <div class="dt-statusbar">
                <div class="flex items-center gap-2">
                    <span class="dt-statusbar-dot {{ $previewError !== '' ? 'error' : '' }}"></span>
                    <span>{{ $this->previewStatusLabel() }}</span>
                </div>
                <div class="dt-statusbar-right">
                    <span>{{ str($activeTab)->headline() }}</span>
                    <span>{{ $this->previewPageLabel() }}</span>
                    <span>{{ $previewZoom }}%</span>
                </div>
            </div>
        </section>

        <div class="dt-resize-handle"
             :class="{ 'dt-resize-handle--active': dragging }"
             @pointerdown.prevent="startResize($event)"
             data-testid="editor-preview-resize-handle"
             role="separator"
             aria-orientation="vertical"
             aria-label="Resize preview panel"></div>

        <aside class="dt-preview"
               data-testid="preview-panel"
               x-ref="previewPanel"
               data-preview-html="{{ $previewHtml }}"
               data-paper-style="{{ $this->previewPaperDimensionsStyle() }}"
               :style="{ width: previewWidth + '%' }">
            <div class="dt-preview-toolbar">
                <span class="dt-preview-toolbar-label">Preview</span>
                <select wire:model.live="previewMode" class="dt-preview-select" aria-label="Preview data mode">
                    <option value="sample">Sample</option>
                    <option value="real">Real</option>
                </select>
                @if($previewMode === 'real')
                    <select wire:model.live="sourceRecordId" class="dt-preview-select" aria-label="Source record">
                        @if($sourceRecordOptions === [])
                            <option value="" disabled>{{ $this->sourceRecordEmptyLabel() }}</option>
                        @else
                            <option value="">{{ $this->sourceRecordPlaceholder() }}</option>
                            @foreach($sourceRecordOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        @endif
                    </select>
                @endif
                <button type="button"
                        class="dt-toolbar-btn"
                        x-on:click="openInNewWindow()"
                        @disabled($previewHtml === '')
                        data-testid="preview-open-new-window">
                    New window
                </button>
                <div class="dt-preview-zoom">
                    <button type="button" class="dt-zoom-btn" wire:click="adjustPreviewZoom(-10)" aria-label="Zoom out">&minus;</button>
                    <span class="dt-zoom-level">{{ $previewZoom }}%</span>
                    <button type="button" class="dt-zoom-btn" wire:click="adjustPreviewZoom(10)" aria-label="Zoom in">+</button>
                </div>
            </div>

            <div class="relative flex-1 min-h-0 flex flex-col">
                <div wire:loading wire:target="refreshPreview,bodyHtml,headerHtml,footerHtml,stylesCss,previewMode,sourceRecordId,previewZoom,saveSettings" class="dt-preview-loading absolute inset-0 z-10 bg-[var(--content-bg)]/80">
                    Rendering preview…
                </div>

                @if($previewError !== '')
                    <div class="dt-preview-error" data-testid="preview-error">
                        <div class="dt-preview-error-title">Render error</div>
                        {{ $previewError }}
                    </div>
                @elseif($previewPrompt !== '')
                    <div class="dt-preview-prompt" data-testid="preview-prompt">{{ $previewPrompt }}</div>
                @elseif($previewHtml !== '')
                    <div class="dt-preview-frame-wrap">
                        <div class="dt-preview-paper" style="{{ $this->previewPaperStyle() }}">
                            <iframe title="Document preview"
                                    data-testid="preview-iframe"
                                    sandbox=""
                                    class="w-full border-0 bg-white"
                                    style="min-height: inherit; height: 100%;"
                                    srcdoc="{{ $previewHtml }}"></iframe>
                        </div>
                    </div>
                @else
                    <div class="dt-preview-loading">Waiting for preview…</div>
                @endif
            </div>

            <div class="dt-versions"
                 :class="{ 'dt-versions--open': versionsOpen, 'dt-versions--has-diff': @js($showDiffPanel) }"
                 data-testid="version-history">
                <button type="button"
                        class="dt-versions-header"
                        @click="toggleVersions()"
                        :aria-expanded="versionsOpen"
                        data-testid="version-history-toggle">
                    <span class="dt-versions-header-label">Version history</span>
                    <span class="dt-versions-count">{{ $versions->count() }}</span>
                    <svg class="dt-versions-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="dt-versions-body">
                @forelse($versions as $version)
                    <div class="dt-version-row" wire:key="version-{{ $version->id }}">
                        <div class="dt-version-meta">
                            <strong>v{{ $version->version }}</strong>@if($version->change_note)<span class="dt-version-note"> — {{ $version->change_note }}</span>@endif
                        </div>
                        @can('update', $template)
                            <div class="dt-version-actions">
                                <button type="button"
                                        @click="openVersions()"
                                        wire:click="showDiff({{ $version->id }})"
                                        class="dt-version-btn">
                                    View diff
                                </button>
                                <button type="button"
                                        wire:click="restoreVersion({{ $version->id }})"
                                        wire:confirm="Restore version {{ $version->version }}?"
                                        class="dt-version-btn">
                                    Restore
                                </button>
                            </div>
                        @endcan
                    </div>
                @empty
                    <p class="dt-versions-empty">No previous versions yet.</p>
                @endforelse

                @if($showDiffPanel)
                    <div class="dt-diff-panel" data-testid="version-diff-panel" wire:key="version-diff-panel">
                        <div class="dt-diff-header">
                            <div class="min-w-0">
                                <div class="font-medium">Body diff</div>
                                <div class="dt-diff-meta">{{ $diffLeftLabel }} → {{ $diffRightLabel }}</div>
                            </div>
                            <button type="button" wire:click="closeDiff" class="s-btn s-btn-ghost s-btn-xs">Close</button>
                        </div>
                        <div class="dt-diff-body">
                            @if($diffOps === [])
                                <div class="dt-diff-line dt-diff-equal">
                                    <span class="dt-diff-prefix">&nbsp;</span>
                                    <span>No differences in saved body content.</span>
                                </div>
                            @else
                                @foreach($diffOps as $index => $op)
                                    @if($op['type'] === 'collapsed')
                                        <div class="dt-diff-line dt-diff-collapsed" wire:key="diff-op-{{ $index }}">
                                            … {{ $op['count'] }} unchanged lines …
                                        </div>
                                    @elseif($op['type'] === 'equal')
                                        @foreach($op['lines'] as $lineIndex => $line)
                                            <div class="dt-diff-line dt-diff-equal" wire:key="diff-op-{{ $index }}-eq-{{ $lineIndex }}">
                                                <span class="dt-diff-prefix">&nbsp;</span>
                                                <span>{!! htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') !!}</span>
                                            </div>
                                        @endforeach
                                    @elseif($op['type'] === 'delete')
                                        @foreach($op['lines'] as $lineIndex => $line)
                                            <div class="dt-diff-line dt-diff-delete" wire:key="diff-op-{{ $index }}-del-{{ $lineIndex }}">
                                                <span class="dt-diff-prefix">−</span>
                                                <span>{!! htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') !!}</span>
                                            </div>
                                        @endforeach
                                    @elseif($op['type'] === 'insert')
                                        @foreach($op['lines'] as $lineIndex => $line)
                                            <div class="dt-diff-line dt-diff-insert" wire:key="diff-op-{{ $index }}-ins-{{ $lineIndex }}">
                                                <span class="dt-diff-prefix">+</span>
                                                <span>{!! htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') !!}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endif
                </div>
            </div>
        </aside>
    </div>

    <x-signals.modal wire:key="document-template-modal-page-setup" name="document-template-page-setup" title="Page, branding & numbering" size="md" :teleport="false">
        <p class="mb-3 text-sm text-[var(--text-muted)]">
            Configure page dimensions, branding overrides, and document numbering for this template.
        </p>

        <form id="document-template-page-setup-form" wire:submit="saveSettings" class="space-y-0">
            <div class="dt-setup-section">
                <h3 class="dt-setup-heading">Page layout</h3>
                <div class="grid grid-cols-2 gap-2">
                    <flux:select wire:model.live="pageConfig.page_size" label="Size">
                        <flux:select.option value="A4">A4</flux:select.option>
                        <flux:select.option value="A5">A5</flux:select.option>
                        <flux:select.option value="Letter">Letter</flux:select.option>
                        <flux:select.option value="A3">A3</flux:select.option>
                        <flux:select.option value="Custom">Custom</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="pageConfig.page_orientation" label="Orientation">
                        <flux:select.option value="portrait">Portrait</flux:select.option>
                        <flux:select.option value="landscape">Landscape</flux:select.option>
                    </flux:select>
                </div>
                @if(strtoupper((string) ($pageConfig['page_size'] ?? '')) === 'CUSTOM')
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <flux:input wire:model="pageDisplayWidth" label="Width ({{ $this->pageSetupUnitLabel() }})" type="number" min="0" step="0.01" />
                        <flux:input wire:model="pageDisplayHeight" label="Height ({{ $this->pageSetupUnitLabel() }})" type="number" min="0" step="0.01" />
                    </div>
                @endif
                <div class="mt-2">
                    <flux:select wire:model.live="pageConfig.display_unit" label="Units">
                        <flux:select.option value="mm">Millimetres (mm)</flux:select.option>
                        <flux:select.option value="in">Inches (in)</flux:select.option>
                    </flux:select>
                </div>
                <p class="mt-2 mb-1 text-[10px] text-[var(--text-muted)]">Margins ({{ $this->pageSetupUnitLabel() }})</p>
                <div class="dt-margin-grid">
                    <div class="dt-margin-wrap">
                        <label for="margin-top">T</label>
                        <flux:input id="margin-top" wire:model="pageDisplayMargins.top" type="number" min="0" step="0.01" />
                    </div>
                    <div class="dt-margin-wrap">
                        <label for="margin-right">R</label>
                        <flux:input id="margin-right" wire:model="pageDisplayMargins.right" type="number" min="0" step="0.01" />
                    </div>
                    <div class="dt-margin-wrap">
                        <label for="margin-bottom">B</label>
                        <flux:input id="margin-bottom" wire:model="pageDisplayMargins.bottom" type="number" min="0" step="0.01" />
                    </div>
                    <div class="dt-margin-wrap">
                        <label for="margin-left">L</label>
                        <flux:input id="margin-left" wire:model="pageDisplayMargins.left" type="number" min="0" step="0.01" />
                    </div>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <flux:input wire:model="pageDisplayHeaderHeight" label="Header ({{ $this->pageSetupUnitLabel() }})" type="number" min="0" step="0.01" />
                    <flux:input wire:model="pageDisplayFooterHeight" label="Footer ({{ $this->pageSetupUnitLabel() }})" type="number" min="0" step="0.01" />
                </div>
            </div>

            <div class="dt-setup-section">
                <h3 class="dt-setup-heading">Branding</h3>
                <div class="grid grid-cols-2 gap-2">
                    <flux:field>
                        <flux:label>Primary colour</flux:label>
                        <x-signals.colour-picker
                            interactive
                            name="brandingConfig.primary_colour"
                            :color="$brandingConfig['primary_colour'] ?? '#1f2937'"
                        />
                    </flux:field>
                    <flux:field>
                        <flux:label>Secondary colour</flux:label>
                        <x-signals.colour-picker
                            interactive
                            name="brandingConfig.secondary_colour"
                            :color="$brandingConfig['secondary_colour'] ?? '#6b7280'"
                        />
                    </flux:field>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2 items-end">
                    @if($this->isKnownFontFamily())
                        <flux:select wire:model="brandingConfig.font_family" label="Font family">
                            @foreach($this->fontFamilyOptions() as $value => $label)
                                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:field>
                            <flux:label>Font family</flux:label>
                            <flux:input readonly :value="$brandingConfig['font_family'] ?? ''" />
                            <flux:description>Custom font stack stored on this template.</flux:description>
                        </flux:field>
                    @endif
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[var(--text-secondary)]">Logo</label>
                        <input type="file" wire:model="logoUpload" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="block w-full text-xs text-[var(--text-muted)] file:mr-2 file:rounded file:border-0 file:bg-[var(--base)] file:px-2 file:py-1 file:text-xs file:font-medium file:text-[var(--text-primary)]">
                        @if(! empty($brandingConfig['logo_path']))
                            <p class="mt-1 truncate text-[10px] text-[var(--text-muted)]">{{ $brandingConfig['logo_path'] }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if($sequence)
                <div class="dt-setup-section">
                    <h3 class="dt-setup-heading">Numbering</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <flux:input wire:model="sequencePrefix" label="Prefix" />
                        <flux:input wire:model="sequenceSuffix" label="Suffix" />
                        <flux:input wire:model="sequenceNextNumber" label="Next number" type="number" min="1" />
                        <flux:input wire:model="sequencePadding" label="Padding" type="number" min="1" max="12" />
                        <flux:switch wire:model="sequenceIncludeYear" label="Include year" />
                        <flux:switch wire:model="sequenceIncludeMonth" label="Include month" />
                    </div>
                </div>
            @endif
        </form>

        <x-slot:footer>
            <button type="button"
                    x-on:click="$dispatch('close-modal', 'document-template-page-setup')"
                    wire:loading.attr="disabled"
                    wire:target="saveSettings"
                    class="s-btn s-btn-ghost s-btn-sm">
                Cancel
            </button>
            <button type="submit"
                    form="document-template-page-setup-form"
                    wire:loading.attr="disabled"
                    wire:target="saveSettings"
                    class="s-btn s-btn-sm s-btn-primary">
                <span wire:loading.remove wire:target="saveSettings">Save</span>
                <span wire:loading wire:target="saveSettings" class="inline-flex items-center gap-1.5">
                    <x-signals.spinner size="xs" /> Saving…
                </span>
            </button>
        </x-slot:footer>
    </x-signals.modal>
</div>
