<?php

use App\Actions\Members\UpdateMember;
use App\Data\Members\UpdateMemberData;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Services\OpportunityTotalsCalculator;
use App\Domain\Tax\Actions\ProcessMemberVatValidation;
use App\Domain\Tax\Actions\RequestMemberVatValidation;
use App\Domain\Tax\Contracts\VatNumberValidator;
use App\Domain\Tax\Data\DocumentTaxLineInput;
use App\Domain\Tax\Data\DocumentTaxRequest;
use App\Domain\Tax\Database\Seeders\TaxZoneRuleSeeder;
use App\Domain\Tax\Enums\TaxBuyerType;
use App\Domain\Tax\Enums\TaxResultType;
use App\Domain\Tax\Jobs\ValidateMemberVatNumber;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxRule;
use App\Domain\Tax\Models\TaxZone;
use App\Domain\Tax\Models\TaxZoneMember;
use App\Domain\Tax\Services\DocumentTaxBreakdown;
use App\Domain\Tax\Services\TaxResolutionContext;
use App\Domain\Tax\Services\TaxResolutionContextHolder;
use App\Domain\Tax\ValueObjects\DocumentTaxSummary;
use App\Domain\Tax\ValueObjects\VatValidationResult;
use App\Enums\PricingRoundingDirection;
use App\Jobs\DeliverWebhook;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Api\WebhookService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

/**
 * @return array{
 *     uk: TaxZone,
 *     euMember: TaxZone,
 *     world: TaxZone,
 *     orgClass: OrganisationTaxClass,
 *     productClass: ProductTaxClass
 * }
 */
function vatValidationTaxInfrastructure(): array
{
    $uk = TaxZone::factory()->uk()->create();
    $euMember = TaxZone::factory()->euMember()->create();
    $world = TaxZone::factory()->world()->create();

    TaxZoneMember::factory()->create(['tax_zone_id' => $uk->id, 'country_code' => 'GB']);
    TaxZoneMember::factory()->create(['tax_zone_id' => $euMember->id, 'country_code' => 'DE']);
    TaxZoneMember::factory()->create(['tax_zone_id' => $world->id, 'country_code' => 'US']);

    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['name' => 'Standard VAT', 'rate' => '20.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    (new TaxZoneRuleSeeder)->run();

    return compact('uk', 'euMember', 'world', 'orgClass', 'productClass');
}

/**
 * @param  list<DocumentTaxLineInput>  $lines
 */
function summarizeMemberOpportunityTax(Opportunity $opportunity, array $lines): DocumentTaxSummary
{
    $context = TaxResolutionContext::fromOpportunity($opportunity->loadMissing(['member', 'store']));

    return app(TaxResolutionContextHolder::class)->using(
        $context,
        fn (): DocumentTaxSummary => app(DocumentTaxBreakdown::class)->summarize(
            new DocumentTaxRequest(
                lines: $lines,
                currencyCode: $opportunity->currency_code ?: 'GBP',
                organisationTaxClassId: $opportunity->member?->sale_tax_class_id,
                effectiveAt: $opportunity->starts_at,
            ),
        ),
    );
}

describe('VAT validation dispatch', function () {
    it('dispatches a validation job when a member tax number is set', function () {
        Queue::fake();

        $member = Member::factory()->organisation()->create([
            'tax_number' => 'DE111111111',
        ]);

        (new UpdateMember)($member, UpdateMemberData::from([
            'tax_number' => 'DE222222222',
        ]));

        Queue::assertPushed(ValidateMemberVatNumber::class, fn (ValidateMemberVatNumber $job): bool => $job->memberId === $member->id);
    });

    it('does not dispatch a validation job when unrelated member fields change', function () {
        Queue::fake();

        $member = Member::factory()->organisation()->create([
            'tax_number' => 'DE111111111',
            'name' => 'Original Name',
        ]);

        (new UpdateMember)($member, UpdateMemberData::from([
            'name' => 'Updated Name',
        ]));

        Queue::assertNotPushed(ValidateMemberVatNumber::class);
    });

    it('marks validation as pending when a tax number changes', function () {
        Queue::fake();

        $member = Member::factory()->organisation()->create([
            'tax_number' => 'DE111111111',
            'tax_number_validated' => true,
            'tax_number_validated_at' => now(),
            'tax_number_validation' => ['source' => 'vies'],
        ]);

        (new UpdateMember)($member, UpdateMemberData::from([
            'tax_number' => 'DE222222222',
        ]));

        $member->refresh();

        expect($member->tax_number_validated)->toBeNull()
            ->and($member->tax_number_validated_at)->toBeNull()
            ->and($member->tax_number_validation)->toBeNull();
    });
});

describe('manual VAT re-check', function () {
    it('queues validation when manually requested', function () {
        Queue::fake();

        $member = Member::factory()->organisation()->create([
            'tax_number' => 'DE123456789',
            'tax_number_validated' => false,
        ]);

        app(RequestMemberVatValidation::class)($member);

        Queue::assertPushed(ValidateMemberVatNumber::class);
        expect($member->fresh()->tax_number_validated)->toBeNull();
    });

    it('exposes validation status and re-check control on the member edit form', function () {
        Queue::fake();
        config(['signals.installed' => true, 'signals.setup_complete' => true]);

        $member = Member::factory()->organisation()->create([
            'tax_number' => 'DE123456789',
            'tax_number_validated' => true,
        ]);

        Volt::test('members.form', ['member' => $member])
            ->assertSee('Valid')
            ->assertSee('Re-check VAT')
            ->call('recheckVatValidation')
            ->assertSet('taxNumberValidated', null);

        Queue::assertPushed(ValidateMemberVatNumber::class);
    });
});

describe('member VAT validation API surface', function () {
    it('exposes validation state on the member payload', function () {
        $member = Member::factory()->organisation()->create([
            'tax_number' => 'DE123456789',
            'tax_number_validated' => true,
            'tax_number_validated_at' => now(),
            'tax_number_validation' => [
                'trader_name' => 'Example GmbH',
                'trader_address' => 'Berlin',
                'source' => 'vies',
                'country_code' => 'DE',
                'vat_number' => '123456789',
            ],
        ]);

        $token = $this->user->createToken('test', ['members:read'])->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/members/{$member->id}")
            ->assertSuccessful()
            ->assertJsonPath('member.membership.tax_number_validated', true)
            ->assertJsonPath('member.membership.tax_number_validation.trader_name', 'Example GmbH');
    });
});

describe('VAT validation webhook', function () {
    it('fires member.vat_validated after processing', function () {
        Queue::fake();

        Webhook::factory()->create([
            'events' => ['member.vat_validated'],
            'is_active' => true,
        ]);

        $member = Member::factory()->organisation()->create([
            'tax_number' => 'DE123456789',
        ]);

        $validator = Mockery::mock(VatNumberValidator::class);
        $validator->shouldReceive('validate')
            ->once()
            ->andReturn(new VatValidationResult(
                isValid: true,
                source: 'vies',
                traderName: 'Example GmbH',
                traderAddress: 'Berlin',
                countryCode: 'DE',
                vatNumber: '123456789',
            ));
        app()->instance(VatNumberValidator::class, $validator);

        app(ProcessMemberVatValidation::class)($member);

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'member.vat_validated');
    });

    it('ProcessMemberVatValidation returns early when tax number is blank', function () {
        $member = Member::factory()->organisation()->create(['tax_number' => '   ']);

        // Refresh so DB-default columns (e.g. bookable) are populated, matching how
        // the action receives a DB-loaded member in production.
        $result = app(ProcessMemberVatValidation::class)($member->refresh());

        expect($result->membership['tax_number_validated'])->toBeNull();
    });

    it('registers member.vat_validated in the webhook catalogue', function () {
        expect(WebhookService::EVENTS)->toContain('member.vat_validated');
    });
});

describe('validated VAT and reverse charge eligibility', function () {
    it('applies reverse charge only when the VAT number is validated', function () {
        $zones = vatValidationTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id]);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['euMember']->id,
            'buyer_type' => TaxBuyerType::Business->value,
            'tax_number' => 'DE123456789',
            'tax_number_validated' => true,
        ]);
        $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
            'currency_code' => 'GBP',
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());

        $summary = summarizeMemberOpportunityTax($opportunity->refresh(), [
            new DocumentTaxLineInput(lineKey: 'item:1', netMinor: 10_000, productTaxClassId: $zones['productClass']->id),
        ]);

        expect($summary->groups[0]->resultType)->toBe(TaxResultType::ReverseCharge)
            ->and($summary->groups[0]->taxTotal)->toBe(0);
    });

    it('falls back to standard VAT when the VAT number is present but not validated', function (mixed $validatedState) {
        $zones = vatValidationTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id]);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['euMember']->id,
            'buyer_type' => TaxBuyerType::Business->value,
            'tax_number' => 'DE123456789',
            'tax_number_validated' => $validatedState,
        ]);
        $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
            'currency_code' => 'GBP',
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());

        $summary = summarizeMemberOpportunityTax($opportunity->refresh(), [
            new DocumentTaxLineInput(lineKey: 'item:1', netMinor: 10_000, productTaxClassId: $zones['productClass']->id),
        ]);

        expect($summary->groups[0]->resultType)->toBe(TaxResultType::Standard)
            ->and($summary->groups[0]->taxTotal)->toBe(2_000);
    })->with([
        'pending' => null,
        'invalid' => false,
    ]);
});
