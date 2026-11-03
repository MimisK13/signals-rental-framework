<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Documents\Contracts\DocumentRenderer;
use App\Domain\Documents\Database\Seeders\DocumentTemplateSeeder;
use App\Domain\Documents\Enums\DocumentOutputFormat;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\DocumentRenderContext;
use App\Domain\Documents\Services\DocumentResolverRegistry;
use App\Domain\Documents\Services\ReturnListDataResolver;
use App\Domain\Documents\Services\StatementDataResolver;
use App\Domain\Documents\Services\SystemDocumentTemplates;
use App\Domain\Members\Models\Member;
use App\Domain\Pricing\Enums\PricingRoundingDirection;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Enums\OpportunityState;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Stores\Models\Store;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxRule;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
});

/**
 * @return array{store: Store, member: Member, opportunity: Opportunity}
 */
function returnListStatementFixture(): array
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

    $store = Store::factory()->create(['timezone' => 'Europe/London']);
    app(DocumentTemplateSeeder::class)->seedForStore($store);

    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $orgClass->id,
        'account_number' => 'ACC-100',
    ]);

    $product = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);

    $opportunity = Opportunity::factory()->create([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'subject' => 'Return List Job',
        'currency_code' => 'GBP',
        'state' => OpportunityState::Order,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-03T17:00:00Z'),
    ]);

    OpportunityItem::factory()->sale()->create([
        'opportunity_id' => $opportunity->id,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'name' => 'Returned Widget',
        'quantity' => '2',
    ]);

    return compact('store', 'member', 'opportunity');
}

describe('ReturnListDataResolver', function () {
    it('is registered and exposes return-specific fields', function () {
        $registry = app(DocumentResolverRegistry::class);

        expect($registry->has('return_list'))->toBeTrue();

        $tree = collect($registry->fieldTree('return_list'))
            ->firstWhere('name', 'Return Items')['fields'] ?? [];

        expect(collect($tree)->pluck('name'))->toContain('return_item.condition_notes');
    });

    it('resolves return_items from opportunity line items', function () {
        ['opportunity' => $opportunity] = returnListStatementFixture();

        $data = app(ReturnListDataResolver::class)->resolve(
            app(ReturnListDataResolver::class)->loadSource($opportunity->id),
        );

        expect($data['return_items'])->toHaveCount(1)
            ->and($data['return_items'][0]['name'])->toBe('Returned Widget')
            ->and($data['return_items'][0]['is_damaged'])->toBeFalse();
    });

    it('renders a PDF from the seeded return_list system template via resolverType()', function () {
        ['store' => $store, 'opportunity' => $opportunity] = returnListStatementFixture();

        $template = DocumentTemplate::query()
            ->where('store_id', $store->id)
            ->where('document_type', 'return_list')
            ->firstOrFail();

        expect($template->resolverType())->toBe('return_list');

        $result = app(DocumentRenderer::class)->render(
            $template->fresh('store'),
            $template->resolverType(),
            $opportunity->id,
            DocumentOutputFormat::Pdf,
        );

        expect($result->success)->toBeTrue()
            ->and($result->pdfBinary)->toStartWith('%PDF');
    });
});

describe('StatementDataResolver', function () {
    it('is registered under the statement resolver key', function () {
        expect(app(DocumentResolverRegistry::class)->has('statement'))->toBeTrue()
            ->and(app(StatementDataResolver::class)->type())->toBe('statement');
    });

    it('resolves member statement data through the registry by statement type', function () {
        ['store' => $store, 'member' => $member] = returnListStatementFixture();

        app(DocumentRenderContext::class)->storeId = $store->id;

        $data = app(DocumentResolverRegistry::class)->resolve('statement', $member->id);

        expect($data['member']['account_number'])->toBe('ACC-100')
            ->and($data['store']['name'])->toBe($store->name)
            ->and($data['invoices'])->toBe([])
            ->and($data['payments'])->toBe([]);
    });

    it('resolves member statement data with empty invoice lines', function () {
        ['store' => $store, 'member' => $member] = returnListStatementFixture();

        app(DocumentRenderContext::class)->storeId = $store->id;

        $data = app(StatementDataResolver::class)->resolve(
            app(StatementDataResolver::class)->loadSource($member->id),
        );

        expect($data['member']['account_number'])->toBe('ACC-100')
            ->and($data['store']['name'])->toBe($store->name)
            ->and($data['invoices'])->toBe([])
            ->and($data['payments'])->toBe([]);
    });

    it('renders a PDF from the seeded statement system template via resolverType()', function () {
        ['store' => $store, 'member' => $member] = returnListStatementFixture();

        $template = DocumentTemplate::query()
            ->where('store_id', $store->id)
            ->where('document_type', 'statement')
            ->firstOrFail();

        expect($template->resolverType())->toBe('statement');

        $result = app(DocumentRenderer::class)->render(
            $template->fresh('store'),
            $template->resolverType(),
            $member->id,
            DocumentOutputFormat::Pdf,
        );

        expect($result->success)->toBeTrue()
            ->and($result->pdfBinary)->toStartWith('%PDF');
    });
});

describe('return_list and statement document type API', function () {
    beforeEach(function () {
        Sanctum::actingAs(User::factory()->owner()->create(), ['*']);
    });

    it('lists statement and return_list in document_types', function () {
        $types = collect($this->getJson('/api/v1/document_types')->json('document_types'))
            ->pluck('type')
            ->all();

        expect($types)->toContain('statement', 'return_list');
    });

    it('returns grouped fields for the statement document type', function () {
        $response = $this->getJson('/api/v1/document_types/statement/fields')
            ->assertOk()
            ->assertJsonPath('document_type', 'statement');

        $fieldNames = collect($response->json('groups'))
            ->flatMap(fn (array $group): array => collect($group['fields'])->pluck('name')->all())
            ->all();

        expect($fieldNames)->toContain('member.account_number', 'invoices', 'payments');
    });
});

describe('system template seeding for new types', function () {
    it('includes return_list and statement in core types', function () {
        expect(SystemDocumentTemplates::CORE_TYPES)->toContain('return_list', 'statement');
    });

    it('seeds return_list and statement templates for new stores', function () {
        $store = Store::factory()->create();

        app(DocumentTemplateSeeder::class)->seedForStore($store);

        foreach (['return_list', 'statement'] as $type) {
            expect(DocumentTemplate::query()
                ->where('store_id', $store->id)
                ->where('document_type', $type)
                ->where('is_system', true)
                ->exists())->toBeTrue();
        }
    });
});
