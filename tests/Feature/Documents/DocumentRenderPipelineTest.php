<?php

use App\Actions\Documents\GenerateDocument;
use App\Contracts\Documents\DocumentRenderer;
use App\Data\Documents\GenerateDocumentData;
use App\Enums\DocumentOutputFormat;
use App\Enums\DocumentStatus;
use App\Enums\OpportunityState;
use App\Enums\PricingRoundingDirection;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Documents\Drivers\BrowsershotDriver;
use App\Services\Documents\Drivers\DompdfDriver;
use App\Services\Documents\PdfDriverManager;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
    Storage::fake('public');
});

/**
 * @return array{store: Store, opportunity: Opportunity, template: DocumentTemplate}
 */
function renderPipelineFixture(): array
{
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    $store = Store::factory()->create([
        'timezone' => 'Europe/London',
        'brand_primary_colour' => '#AABBCC',
        'brand_secondary_colour' => '#112233',
        'brand_font_family' => 'Helvetica',
    ]);

    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $orgClass->id,
    ]);

    $product = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);

    $opportunity = Opportunity::factory()->create([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'subject' => 'Summer Festival Quote',
        'currency_code' => 'GBP',
        'state' => OpportunityState::Quotation,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-03T17:00:00Z'),
        'charge_total' => 10_000,
        'tax_total' => 2_000,
    ]);

    OpportunityItem::factory()->sale()->create([
        'opportunity_id' => $opportunity->id,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'name' => 'Test Product Line',
        'quantity' => '2',
        'unit_price' => 5_000,
        'total' => 10_000,
    ]);

    $template = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'model_type' => Opportunity::class,
        'body_html' => '<h1>{{ $opportunity->subject }}</h1><p>Total: {{ $opportunity->grand_total }}</p>',
        'header_html' => '<span class="brand">Quote</span>',
        'footer_html' => '<small>Footer</small>',
        'styles_css' => '.brand { color: var(--primary-colour); }',
        'branding_config' => [
            'primary_colour' => '#FF0000',
        ],
    ]);

    DocumentSequence::factory()->create([
        'store_id' => $store->id,
        'document_type' => 'quote',
        'prefix' => 'QUO-',
        'include_year' => false,
        'padding' => 4,
        'next_number' => 1,
    ]);

    return compact('store', 'opportunity', 'template');
}

describe('DocumentRenderPipeline', function () {
    it('renders an opportunity to a non-empty PDF via Dompdf', function () {
        ['opportunity' => $opportunity, 'template' => $template] = renderPipelineFixture();

        $result = app(DocumentRenderer::class)->render(
            $template->fresh('store'),
            'opportunity',
            $opportunity->id,
            DocumentOutputFormat::Pdf,
        );

        expect($result->success)->toBeTrue()
            ->and($result->pdfBinary)->not->toBeEmpty()
            ->and($result->pdfBinary)->toStartWith('%PDF');
    });

    it('returns preview HTML with resolved fields and branding CSS variables', function () {
        ['opportunity' => $opportunity, 'template' => $template] = renderPipelineFixture();

        $result = app(DocumentRenderer::class)->render(
            $template->fresh('store'),
            'opportunity',
            $opportunity->id,
            DocumentOutputFormat::Preview,
        );

        expect($result->success)->toBeTrue()
            ->and($result->html)->toContain('Summer Festival Quote')
            ->and($result->html)->toContain('120.00')
            ->and($result->html)->toContain('--primary-colour: #FF0000')
            ->and($result->html)->toContain('--font-family: Helvetica');
    });

    it('reproduces a document from a frozen rendered_data snapshot after the source mutates', function () {
        ['opportunity' => $opportunity, 'template' => $template] = renderPipelineFixture();

        $document = app(GenerateDocument::class)(new GenerateDocumentData(
            document_template_id: $template->id,
            source_id: $opportunity->id,
        ));

        $originalSubject = $document->rendered_data['opportunity']['subject'];
        $opportunity->update(['subject' => 'Mutated Subject']);

        $snapshotResult = app(DocumentRenderer::class)->renderFromSnapshot(
            $template->fresh('store'),
            $document->rendered_data,
            DocumentOutputFormat::Preview,
        );

        expect($snapshotResult->success)->toBeTrue()
            ->and($snapshotResult->html)->toContain($originalSubject)
            ->and($snapshotResult->html)->not->toContain('Mutated Subject');
    });

    it('allocates a document number exactly once per generation', function () {
        ['opportunity' => $opportunity, 'template' => $template, 'store' => $store] = renderPipelineFixture();

        $sequence = DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', 'quote')
            ->firstOrFail();

        expect($sequence->next_number)->toBe(1);

        $first = app(GenerateDocument::class)(new GenerateDocumentData(
            document_template_id: $template->id,
            source_id: $opportunity->id,
        ));

        $second = app(GenerateDocument::class)(new GenerateDocumentData(
            document_template_id: $template->id,
            source_id: $opportunity->id,
        ));

        expect($sequence->refresh()->next_number)->toBe(3)
            ->and($first->document_number)->toBe('QUO-0001')
            ->and($second->document_number)->toBe('QUO-0002')
            ->and(Document::query()->count())->toBe(2);
    });

    it('persists rendered_data and pdf_path when generating a document', function () {
        ['opportunity' => $opportunity, 'template' => $template] = renderPipelineFixture();

        $document = app(GenerateDocument::class)(new GenerateDocumentData(
            document_template_id: $template->id,
            source_id: $opportunity->id,
        ));

        expect($document->status)->toBe(DocumentStatus::Draft)
            ->and($document->rendered_data['opportunity']['subject'])->toBe('Summer Festival Quote')
            ->and($document->pdf_path)->not->toBeNull()
            ->and($document->template_version)->toBe($template->version)
            ->and(Storage::disk('public')->exists($document->pdf_path))->toBeTrue();
    });
});

describe('PdfDriverManager', function () {
    it('selects the configured PDF driver', function () {
        config(['documents.pdf_driver' => 'dompdf']);
        expect(app(PdfDriverManager::class)->driver())->toBeInstanceOf(DompdfDriver::class);

        config(['documents.pdf_driver' => 'browsershot']);
        expect(app(PdfDriverManager::class)->driver())->toBeInstanceOf(BrowsershotDriver::class);
    });

    it('throws for unknown driver names', function () {
        expect(fn () => app(PdfDriverManager::class)->driver('missing-driver'))
            ->toThrow(InvalidArgumentException::class, 'Unknown document PDF driver');
    });
});
