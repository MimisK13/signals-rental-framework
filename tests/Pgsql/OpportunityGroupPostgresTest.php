<?php

use App\Domain\Projects\Opportunities\Actions\AddOpportunityGroup;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\RestructureOpportunityItems;
use App\Domain\Projects\Opportunities\Data\AddOpportunityGroupData;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\RestructureOpportunityItemsData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Concerns\UsesPostgres;
use Thunk\Verbs\Facades\Verbs;

/*
|--------------------------------------------------------------------------
| PostgreSQL unified group-row lane (replaces OpportunitySectionPostgresTest)
|--------------------------------------------------------------------------
|
| Retired: section_id FK nullOnDelete and section_id replay preservation — the
| unified model nests lines under group rows via materialised `path` instead.
| This lane confirms nested paths survive a Verbs replay on real Postgres.
|
| Run the lane:
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true]);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

function pgGroupOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'PG Grouped']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('preserves nested group paths across a Verbs replay on Postgres', function () {
    $opportunity = pgGroupOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Rig']));
    $group = $opportunity->refresh()->items()->where('path', '0001')->firstOrFail();

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Truss',
        'quantity' => '1',
        'unit_price' => 5000,
        'parent_path' => $group->path,
    ]));
    $line = $opportunity->refresh()->items()->where('path', '00010001')->firstOrFail();

    expect($line->parentPath())->toBe($group->path);

    Verbs::replay();

    expect($line->refresh()->path)->toBe('00010001')
        ->and($line->parentPath())->toBe('0001')
        ->and($group->refresh()->path)->toBe('0001');
});

it('persists restructured tree paths on Postgres', function () {
    $opportunity = pgGroupOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'A']));
    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from(['name' => 'B']));

    $groups = $opportunity->refresh()->items()->orderBy('path')->get();
    [$a, $b] = [$groups[0], $groups[1]];

    (new RestructureOpportunityItems)($opportunity, RestructureOpportunityItemsData::from([
        'nodes' => [
            ['id' => $a->id, 'depth' => 1],
            ['id' => $b->id, 'depth' => 2],
        ],
    ]));

    expect(OpportunityItem::query()->whereKey($b->id)->value('path'))->toBe('00010001');

    Verbs::replay();

    expect(OpportunityItem::query()->whereKey($b->id)->value('path'))->toBe('00010001');
});
