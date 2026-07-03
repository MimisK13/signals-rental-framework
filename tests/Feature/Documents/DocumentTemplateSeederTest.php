<?php

use App\Actions\Stores\CreateStore;
use App\Contracts\Documents\DocumentRenderer;
use App\Data\Stores\CreateStoreData;
use App\Enums\DocumentOutputFormat;
use App\Enums\OpportunityState;
use App\Enums\PricingRoundingDirection;
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
use App\Services\Documents\SystemDocumentTemplates;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
});

/**
 * @return array{store: Store, opportunity: Opportunity}
 */
function seededTemplateRenderFixture(): array
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
        'brand_primary_colour' => '#003366',
        'brand_secondary_colour' => '#666666',
        'brand_font_family' => 'Helvetica',
    ]);

    app(DocumentTemplateSeeder::class)->seedForStore($store);

    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $orgClass->id,
    ]);

    $product = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);

    $opportunity = Opportunity::factory()->create([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'subject' => 'Seeded Template Render Test',
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
        'name' => 'Render Line',
        'quantity' => '1',
        'unit_price' => 10_000,
        'total' => 10_000,
    ]);

    foreach (SystemDocumentTemplates::CORE_TYPES as $documentType) {
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => $documentType,
            'prefix' => strtoupper(substr(str_replace('_', '', $documentType), 0, 3)).'-',
            'include_year' => false,
            'padding' => 4,
            'next_number' => 1,
        ]);
    }

    return compact('store', 'opportunity');
}

describe('DocumentTemplateSeeder', function () {
    it('seeds one system default template per core document type', function () {
        $store = Store::factory()->create();

        app(DocumentTemplateSeeder::class)->seedForStore($store);

        expect(DocumentTemplate::query()->where('store_id', $store->id)->count())->toBe(count(SystemDocumentTemplates::CORE_TYPES));

        foreach (SystemDocumentTemplates::CORE_TYPES as $type) {
            $template = DocumentTemplate::query()
                ->where('store_id', $store->id)
                ->where('document_type', $type)
                ->first();

            expect($template)->not->toBeNull()
                ->and($template->is_system)->toBeTrue()
                ->and($template->is_default)->toBeTrue()
                ->and($template->slug)->toBe('system-'.$type);
        }
    });

    it('is idempotent when seeding the same store twice', function () {
        $store = Store::factory()->create();
        $seeder = app(DocumentTemplateSeeder::class);

        $seeder->seedForStore($store);
        $seeder->seedForStore($store);

        expect(DocumentTemplate::query()->where('store_id', $store->id)->count())
            ->toBe(count(SystemDocumentTemplates::CORE_TYPES));
    });

    it('seeds templates when a store is created through CreateStore', function () {
        $store = (new CreateStore)(new CreateStoreData(
            name: 'North Depot',
            country_code: 'GB',
        ));

        expect(DocumentTemplate::query()->where('store_id', $store->id)->count())
            ->toBe(count(SystemDocumentTemplates::CORE_TYPES));
    });

    it('renders a valid non-empty PDF for every seeded system template type', function () {
        ['store' => $store, 'opportunity' => $opportunity] = seededTemplateRenderFixture();

        foreach (SystemDocumentTemplates::CORE_TYPES as $documentType) {
            $template = DocumentTemplate::query()
                ->where('store_id', $store->id)
                ->where('document_type', $documentType)
                ->firstOrFail();

            $result = app(DocumentRenderer::class)->render(
                $template->fresh('store'),
                'opportunity',
                $opportunity->id,
                DocumentOutputFormat::Pdf,
            );

            expect($result->success)->toBeTrue("Expected {$documentType} template to render successfully")
                ->and($result->pdfBinary)->not->toBeEmpty()
                ->and($result->pdfBinary)->toStartWith('%PDF');
        }
    });
});
