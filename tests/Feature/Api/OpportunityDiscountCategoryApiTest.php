<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityStatus;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

function discountApiToken(User $user): string
{
    return $user->createToken('test', ['opportunities:write'])->plainTextToken;
}

function discountCategoryOpportunity(User $actor): Opportunity
{
    Auth::login($actor);

    try {
        $category = DiscountCategory::factory()->create();
        DiscountRule::factory()->for($category)->blanket()->create(['discount_percent' => '10.00']);

        $member = Member::factory()->organisation()->create([
            'discount_category_id' => $category->id,
        ]);
        $product = Product::factory()->rental()->create();

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Discount API',
            'member_id' => $member->id,
            'starts_at' => '2026-07-01T09:00:00Z',
            'ends_at' => '2026-07-05T17:00:00Z',
        ]));
        $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'discount_percent' => '25.00',
        ]));

        return $opportunity->fresh('items');
    } finally {
        Auth::logout();
    }
}

it('applies the discount category via the API endpoint', function () {
    $opportunity = discountCategoryOpportunity($this->owner);
    $item = $opportunity->items->firstOrFail();

    expect($item->is_discount_manual)->toBeTrue();

    $response = $this->withToken(discountApiToken($this->owner))
        ->postJson("/api/v1/opportunities/{$opportunity->id}/apply_discount_category", [
            'opportunity_item_ids' => [$item->id],
            'reset_manual' => true,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('opportunity.items.0.discount_percent', '10.00')
        ->assertJsonPath('opportunity.items.0.is_discount_manual', false);
});

it('runs the apply_discount_category transition through the guard pipeline', function () {
    $opportunity = discountCategoryOpportunity($this->owner);

    Auth::login($this->owner);

    $context = new TransitionContext(
        transition: 'opportunity.apply_discount_category',
        opportunity: $opportunity,
        permission: 'opportunities.edit',
    );

    expect(app(GuardPipeline::class)->check($context)->allowed)->toBeTrue();
});

it('blocks apply_discount_category on a closed opportunity via Verbs validate', function () {
    $opportunity = discountCategoryOpportunity($this->owner);

    Auth::login($this->owner);
    (new ConvertToQuotation)($opportunity->fresh());
    (new ChangeOpportunityStatus)($opportunity->fresh(), OpportunityStatus::QuotationLost);
    Auth::logout();

    $response = $this->withToken(discountApiToken($this->owner))
        ->postJson("/api/v1/opportunities/{$opportunity->id}/apply_discount_category", [
            'reset_manual' => true,
        ]);

    $response->assertUnprocessable();
});

it('requires opportunities:write ability for the API endpoint', function () {
    $opportunity = discountCategoryOpportunity($this->owner);
    $readOnly = $this->owner->createToken('read', ['opportunities:read'])->plainTextToken;

    $this->withToken($readOnly)
        ->postJson("/api/v1/opportunities/{$opportunity->id}/apply_discount_category")
        ->assertForbidden();
});

it('denies apply_discount_category when the caller lacks edit permission', function () {
    $opportunity = discountCategoryOpportunity($this->owner);

    $readOnlyUser = User::factory()->create();
    $readOnlyUser->assignRole('Read Only');

    Auth::login($readOnlyUser);

    $context = new TransitionContext(
        transition: 'opportunity.apply_discount_category',
        opportunity: $opportunity,
        permission: 'opportunities.edit',
    );

    expect(fn () => app(GuardPipeline::class)->run($context))
        ->toThrow(AuthorizationException::class);
});
