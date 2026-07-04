<?php

use App\Models\CreditNote;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\User;
use App\Services\Documents\DocumentSampleData;
use App\Services\Documents\PageDimensions;
use App\Services\Documents\PdfPageLayout;
use App\Services\Documents\SystemDocumentTemplates;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);

    $this->store = Store::factory()->create();
    app(DocumentTemplateSeeder::class)->seedForStore($this->store);
});

it('previews every seeded document type with sample data', function () {
    foreach (SystemDocumentTemplates::CORE_TYPES as $documentType) {
        $template = DocumentTemplate::query()
            ->where('store_id', $this->store->id)
            ->where('document_type', $documentType)
            ->firstOrFail();

        Volt::test('document-templates.edit', ['documentTemplate' => $template])
            ->assertSet('previewMode', 'sample')
            ->assertSet('previewError', '')
            ->assertSet('previewHtml', fn (string $html): bool => trim($html) !== '');
    }
});

it('previews credit note templates with credit note sample context', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'credit_note')
        ->firstOrFail();

    Volt::test('document-templates.edit', ['documentTemplate' => $template])
        ->assertSet('previewError', '')
        ->assertSet('previewHtml', fn (string $html): bool => str_contains($html, 'CRN-0001') || str_contains($html, 'Sample'));
});

it('shows a neutral prompt when real preview mode has no source record selected', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'credit_note')
        ->firstOrFail();

    Volt::test('document-templates.edit', ['documentTemplate' => $template])
        ->set('previewMode', 'real')
        ->set('sourceRecordId', null)
        ->call('refreshPreview')
        ->assertSet('previewError', '')
        ->assertSet('previewPrompt', 'Select a credit note to preview real data.')
        ->assertSet('previewHtml', '');
});

it('lists store credit notes and previews credit note templates with real data', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'credit_note')
        ->firstOrFail();

    $invoice = Invoice::factory()->issued()->create([
        'store_id' => $this->store->id,
        'subtotal' => 10_000,
        'tax_total' => 2_000,
        'total' => 12_000,
    ]);

    $creditNote = CreditNote::factory()->create([
        'invoice_id' => $invoice->id,
        'number' => 'CN-REAL-PREVIEW-001',
    ]);

    Volt::test('document-templates.edit', ['documentTemplate' => $template])
        ->assertSet('sourceRecordOptions', fn (array $options): bool => collect($options)->contains(
            fn (array $option): bool => $option['id'] === $creditNote->id
                && str_contains($option['label'], 'CN-REAL-PREVIEW-001'),
        ))
        ->set('previewMode', 'real')
        ->set('sourceRecordId', $creditNote->id)
        ->assertSet('previewError', '')
        ->assertSet('previewHtml', fn (string $html): bool => str_contains($html, 'CN-REAL-PREVIEW-001'));
});

it('shows a sanitised render error for broken template content without failing the editor', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'quote')
        ->firstOrFail();

    Volt::test('document-templates.edit', ['documentTemplate' => $template])
        ->set('bodyHtml', '{{ $undefined_preview_variable_xyz }}')
        ->call('refreshPreview')
        ->assertSet('previewHtml', '')
        ->assertSet('previewError', fn (string $error): bool => str_contains($error, 'Undefined variable')
            && ! str_contains($error, 'storage/')
            && ! str_contains($error, 'Stack trace'));
});

it('round-trips page setup through template storage and pdf layout', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'quote')
        ->firstOrFail();

    Volt::test('document-templates.edit', ['documentTemplate' => $template])
        ->set('pageConfig.page_size', 'Letter')
        ->set('pageConfig.page_orientation', 'landscape')
        ->set('pageDisplayMargins.top', 20)
        ->set('pageDisplayMargins.right', 15)
        ->set('pageDisplayMargins.bottom', 18)
        ->set('pageDisplayMargins.left', 12)
        ->set('pageDisplayHeaderHeight', 30)
        ->set('pageDisplayFooterHeight', 18)
        ->call('saveSettings')
        ->assertHasNoErrors();

    $template->refresh();

    expect($template->page_config['page_size'])->toBe('Letter')
        ->and($template->page_config['page_orientation'])->toBe('landscape')
        ->and($template->page_config['margins']['top'])->toBe(20)
        ->and($template->page_config['header_height_mm'])->toBe(30)
        ->and(PdfPageLayout::headerZoneMm($template->page_config, true))->toBe(30.0)
        ->and(PdfPageLayout::baseMargins($template->page_config)['left'])->toBe(12.0);

    $margins = PdfPageLayout::pageMargins($template->page_config, true, true);

    expect($margins['top'])->toBe(50.0)
        ->and($margins['bottom'])->toBe(36.0);
});

it('round-trips A5 page setup through template storage and preview dimensions', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'quote')
        ->firstOrFail();

    Volt::test('document-templates.edit', ['documentTemplate' => $template])
        ->set('pageConfig.page_size', 'A5')
        ->set('pageConfig.page_orientation', 'portrait')
        ->call('saveSettings')
        ->assertHasNoErrors()
        ->assertDispatched('close-modal');

    $template->refresh();

    expect($template->page_config['page_size'])->toBe('A5')
        ->and(PageDimensions::sizeMm($template->page_config))
        ->toBe(['width' => 148.0, 'height' => 210.0]);
});

it('round-trips custom page size in inches with canonical millimetre storage', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'quote')
        ->firstOrFail();

    Volt::test('document-templates.edit', ['documentTemplate' => $template])
        ->set('pageConfig.page_size', 'Custom')
        ->set('pageConfig.display_unit', 'in')
        ->set('pageDisplayWidth', 4)
        ->set('pageDisplayHeight', 6)
        ->set('pageDisplayMargins.top', 0.5)
        ->set('pageDisplayHeaderHeight', 1)
        ->set('pageDisplayFooterHeight', 0.75)
        ->call('saveSettings')
        ->assertHasNoErrors();

    $template->refresh();

    expect($template->page_config['display_unit'])->toBe('in')
        ->and($template->page_config['page_size'])->toBe('Custom')
        ->and($template->page_config['page_width_mm'])->toBe(101.6)
        ->and($template->page_config['page_height_mm'])->toBe(152.4)
        ->and($template->page_config['margins']['top'])->toBe(12.7)
        ->and($template->page_config['header_height_mm'])->toBe(25.4)
        ->and($template->page_config['footer_height_mm'])->toBe(19.05);

    Volt::test('document-templates.edit', ['documentTemplate' => $template->fresh()])
        ->assertSet('pageDisplayWidth', 4.0)
        ->assertSet('pageDisplayHeight', 6.0)
        ->assertSet('pageDisplayMargins.top', 0.5);
});

it('provides sample data for every document resolver type', function () {
    $resolverTypes = [
        'opportunity',
        'return_list',
        'statement',
        'receipt',
        'invoice',
        'credit_note',
        'purchase_order',
    ];

    foreach ($resolverTypes as $resolverType) {
        $data = DocumentSampleData::forResolver($resolverType);

        expect($data)->toBeArray();
        expect($data)->not->toBeEmpty();
    }

    expect(DocumentSampleData::forResolver('credit_note'))->toHaveKey('credit_note')
        ->and(DocumentSampleData::forResolver('invoice'))->toHaveKey('invoice')
        ->and(DocumentSampleData::forResolver('return_list'))->toHaveKey('return_items');
});

it('renders with a single root element for Livewire compatibility', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'quote')
        ->firstOrFail();

    $html = Volt::test('document-templates.edit', ['documentTemplate' => $template])->html();

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?><div id="volt-root">'.$html.'</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $wrap = $dom->getElementById('volt-root');
    expect($wrap)->not->toBeNull();

    $rootElements = collect($wrap->childNodes)
        ->filter(fn ($node) => $node instanceof DOMElement)
        ->values()
        ->all();

    expect($rootElements)->toHaveCount(1);
    expect($rootElements[0]->getAttribute('class'))->toContain('dt-editor');
});

it('does not double-escape preview iframe srcdoc content', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'quote')
        ->firstOrFail();

    $html = Volt::test('document-templates.edit', ['documentTemplate' => $template])
        ->assertSet('previewHtml', fn (string $previewHtml): bool => trim($previewHtml) !== '')
        ->html();

    expect(str_contains($html, 'srcdoc="&lt;'))->toBeTrue();
    expect(str_contains($html, 'srcdoc="&amp;lt;'))->toBeFalse();
});
