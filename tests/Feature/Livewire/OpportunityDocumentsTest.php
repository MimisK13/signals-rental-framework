<?php

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
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
});

/**
 * @return array{opportunity: Opportunity, template: DocumentTemplate}
 */
function opportunityDocumentsFixture(): array
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
        'subject' => 'Opportunity Documents Tab',
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
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 10_000,
        'total' => 10_000,
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

    return compact('opportunity', 'template');
}

describe('Opportunity documents tab', function () {
    it('forbids users without documents.view', function () {
        ['opportunity' => $opportunity] = opportunityDocumentsFixture();

        $user = User::factory()->create(['is_owner' => false, 'is_active' => true]);
        $user->givePermissionTo(['opportunities.view']);

        $this->actingAs($user);

        Volt::test('opportunities.documents', ['opportunity' => $opportunity])
            ->assertForbidden();
    });

    it('generates downloads and share links end-to-end', function () {
        ['opportunity' => $opportunity, 'template' => $template] = opportunityDocumentsFixture();

        $user = User::factory()->create(['is_owner' => true, 'is_active' => true]);
        $user->givePermissionTo([
            'opportunities.view',
            'documents.view',
            'documents.create',
            'documents.share',
        ]);

        $this->actingAs($user);

        Volt::test('opportunities.documents', ['opportunity' => $opportunity])
            ->assertSee('Generate document')
            ->set('selectedTemplateId', $template->id)
            ->call('generateDocument')
            ->assertHasNoErrors();

        expect($opportunity->documents()->count())->toBe(1);

        $document = $opportunity->documents()->firstOrFail();

        $this->actingAs($user);

        Volt::test('opportunities.documents', ['opportunity' => $opportunity->fresh()])
            ->assertSee($document->document_number)
            ->assertSee('Download')
            ->call('openShareModal', $document->id)
            ->set('sharePin', '1357')
            ->call('saveShareLink')
            ->assertHasNoErrors();

        $document->refresh();

        expect($document->public_token)->not->toBeNull()
            ->and($document->public_pin_hash)->not->toBeNull();
    });
});
