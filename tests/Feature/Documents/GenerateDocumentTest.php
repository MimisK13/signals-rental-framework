<?php

use App\Actions\Documents\GenerateDocument;
use App\Data\Documents\GenerateDocumentData;
use App\Enums\DocumentOutputFormat;
use App\Enums\OpportunityState;
use App\Enums\PricingRoundingDirection;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
    Storage::fake('public');
});

/**
 * @return array{store: Store, opportunity: Opportunity, template: DocumentTemplate}
 */
function generateDocumentFixture(): array
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

    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);
    $product = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);

    $opportunity = Opportunity::factory()->create([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'subject' => 'Cross-store guard quote',
        'currency_code' => 'GBP',
        'state' => OpportunityState::Quotation,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-03T17:00:00Z'),
        'charge_total' => 10_000,
        'tax_total' => 2_000,
    ]);

    $template = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'model_type' => Opportunity::class,
        'body_html' => '<h1>{{ $opportunity->subject }}</h1>',
        'is_default' => true,
        'is_active' => true,
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

describe('GenerateDocument cross-store guard', function () {
    it('generates a document when the template and source share a store', function () {
        ['opportunity' => $opportunity, 'template' => $template] = generateDocumentFixture();

        $this->actingAs(User::factory()->owner()->create());

        $document = app(GenerateDocument::class)(new GenerateDocumentData(
            document_template_id: $template->id,
            source_id: $opportunity->id,
        ));

        expect($document->store_id)->toBe($template->store_id)
            ->and($document->rendered_data['opportunity']['subject'])->toBe('Cross-store guard quote');
    });

    it('rejects generation when the template belongs to a different store than the source opportunity', function () {
        ['template' => $template] = generateDocumentFixture();
        $foreignStore = Store::factory()->create();
        $foreignOpportunity = Opportunity::factory()->for($foreignStore)->create([
            'subject' => 'Foreign opportunity',
        ]);

        expect($foreignOpportunity->store_id)->not->toBe($template->store_id);

        $this->actingAs(User::factory()->owner()->create());

        expect(fn () => app(GenerateDocument::class)(new GenerateDocumentData(
            document_template_id: $template->id,
            source_id: $foreignOpportunity->id,
        )))->toThrow(ValidationException::class, 'The template does not belong to the source store.');
    });

    it('rejects generation for a scoped user who can access both stores but mixes template and source stores', function () {
        ['store' => $homeStore, 'template' => $homeTemplate] = generateDocumentFixture();
        $foreignStore = Store::factory()->create();
        $foreignOpportunity = Opportunity::factory()->for($foreignStore)->create([
            'subject' => 'Scoped foreign opportunity',
        ]);

        DocumentSequence::factory()->create([
            'store_id' => $foreignStore->id,
            'document_type' => 'quote',
            'prefix' => 'QUO-',
            'include_year' => false,
            'padding' => 4,
            'next_number' => 1,
        ]);

        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id, 'is_owner' => false, 'is_active' => true]);
        $user->givePermissionTo(['documents.create', 'opportunities.view']);
        MemberStore::create(['member_id' => $member->id, 'store_id' => $homeStore->id, 'created_at' => now()]);
        MemberStore::create(['member_id' => $member->id, 'store_id' => $foreignStore->id, 'created_at' => now()]);

        $this->actingAs($user);

        expect(fn () => app(GenerateDocument::class)(new GenerateDocumentData(
            document_template_id: $homeTemplate->id,
            source_id: $foreignOpportunity->id,
            output_format: DocumentOutputFormat::HtmlDownload,
        )))->toThrow(ValidationException::class, 'The template does not belong to the source store.');
    });
});
