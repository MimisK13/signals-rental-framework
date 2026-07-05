<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\DocumentSequence;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityNumberGenerator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

it('generates a zero-padded number on create', function () {
    $store = Store::factory()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'First', 'store_id' => $store->id,
    ]));

    expect(Opportunity::query()->whereKey($created->id)->value('number'))->toBe('0000000001');
});

it('hands out sequential numbers within a store', function () {
    $store = Store::factory()->create();

    $a = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'A', 'store_id' => $store->id]));
    $b = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'B', 'store_id' => $store->id]));

    expect(Opportunity::query()->whereKey($a->id)->value('number'))->toBe('0000000001')
        ->and(Opportunity::query()->whereKey($b->id)->value('number'))->toBe('0000000002');
});

it('scopes the running number per store on fresh installs', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    $a1 = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'A1', 'store_id' => $storeA->id]));
    $b1 = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'B1', 'store_id' => $storeB->id]));
    $a2 = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'A2', 'store_id' => $storeA->id]));

    expect(Opportunity::query()->whereKey($a1->id)->value('number'))->toBe('0000000001')
        ->and(Opportunity::query()->whereKey($b1->id)->value('number'))->toBe('0000000001')
        ->and(Opportunity::query()->whereKey($a2->id)->value('number'))->toBe('0000000002');
});

it('honours number_pad when provisioning the document sequence from settings', function () {
    settings()->set('opportunities.number_pad', 4, 'integer');

    $store = Store::factory()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Padded', 'store_id' => $store->id,
    ]));

    expect(Opportunity::query()->whereKey($created->id)->value('number'))->toBe('0001')
        ->and(DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', OpportunityNumberGenerator::DOCUMENT_TYPE)
            ->value('padding'))->toBe(4);
});

it('continues after legacy global-scope numbers without re-issuing existing values', function () {
    settings()->set('opportunities.number_scope', 'global');

    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    Opportunity::factory()->create(['store_id' => $storeA->id, 'number' => '0000000042']);
    Opportunity::factory()->create(['store_id' => $storeB->id, 'number' => '0000000043']);

    $a = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'A', 'store_id' => $storeA->id]));
    $b = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'B', 'store_id' => $storeB->id]));

    expect(Opportunity::query()->whereKey($a->id)->value('number'))->toBe('0000000044')
        ->and(Opportunity::query()->whereKey($b->id)->value('number'))->toBe('0000000045');
});

it('reproduces the same number on replay', function () {
    $store = Store::factory()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Replayed', 'store_id' => $store->id]));
    $numberBefore = Opportunity::query()->whereKey($created->id)->value('number');

    expect($numberBefore)->toBe('0000000001');

    Verbs::replay();

    expect(Opportunity::query()->whereKey($created->id)->value('number'))->toBe($numberBefore);
});

it('does not violate unique store_id and number when two stores continue from the seeded max', function () {
    settings()->set('opportunities.number_scope', 'global');

    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    Opportunity::factory()->create(['store_id' => $storeA->id, 'number' => '0000000010']);

    (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'A', 'store_id' => $storeA->id]));
    (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'B', 'store_id' => $storeB->id]));

    $duplicateWithinStore = Opportunity::query()
        ->whereIn('store_id', [$storeA->id, $storeB->id])
        ->whereNotNull('number')
        ->selectRaw('store_id, number, COUNT(*) as count')
        ->groupBy('store_id', 'number')
        ->havingRaw('COUNT(*) > 1')
        ->exists();

    expect($duplicateWithinStore)->toBeFalse();
});
