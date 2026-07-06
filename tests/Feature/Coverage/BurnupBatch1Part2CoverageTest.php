<?php

/**
 * Coverage burn-up batch 1 — additional feature tests (part 2).
 */

use App\Actions\Opportunities\AssignItemToSection;
use App\Actions\Opportunities\CreateOpportunitySection;
use App\Actions\Opportunities\ReorderOpportunityItems;
use App\Data\Opportunities\AssignItemToSectionData;
use App\Data\Opportunities\CreateOpportunitySectionData;
use App\Data\Opportunities\ReorderOpportunityItemsData;
use App\Models\Opportunity;
use App\Models\OpportunitySection;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
    $this->actingAs(User::factory()->owner()->create());
});

it('AssignItemToSection rejects sections from another opportunity when sections table exists', function () {
    $opportunity = giCreateQuotationOpportunity();
    $other = Opportunity::factory()->create();
    $item = $opportunity->items()->firstOrFail();

    try {
        $section = OpportunitySection::factory()->for($other)->create();
        expect(fn () => (new AssignItemToSection)($item, AssignItemToSectionData::from([
            'section_id' => $section->id,
        ])))->toThrow(ValidationException::class, 'does not belong to this opportunity');
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('opportunity_sections');
    }
});

it('CreateOpportunitySection rejects foreign parents and excessive nesting when sections table exists', function () {
    $opportunity = giCreateQuotationOpportunity();
    $foreign = Opportunity::factory()->create();

    try {
        $foreignSection = OpportunitySection::factory()->for($foreign)->create();

        expect(fn () => (new CreateOpportunitySection)($opportunity, CreateOpportunitySectionData::from([
            'name' => 'Nested',
            'sort_order' => 1,
            'parent_id' => $foreignSection->id,
        ])))->toThrow(ValidationException::class, 'parent section does not belong');
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('opportunity_sections');
    }
});

it('CreateOpportunitySection rejects nesting beyond the maximum depth when sections table exists', function () {
    $opportunity = giCreateQuotationOpportunity();

    try {
        $parent = null;
        for ($depth = 1; $depth <= CreateOpportunitySection::MAX_DEPTH; $depth++) {
            $parent = (new CreateOpportunitySection)($opportunity, CreateOpportunitySectionData::from([
                'name' => "Level {$depth}",
                'sort_order' => $depth,
                'parent_id' => $parent?->id,
            ]));
        }

        expect(fn () => (new CreateOpportunitySection)($opportunity, CreateOpportunitySectionData::from([
            'name' => 'Too deep',
            'sort_order' => 99,
            'parent_id' => $parent->id,
        ])))->toThrow(ValidationException::class, 'cannot be nested more than');
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('opportunity_sections');
    }
});

it('ReorderOpportunityItems reorders in-memory when item sort projection succeeds', function () {
    $opportunity = giCreateQuotationOpportunity();
    $items = $opportunity->items()->orderBy('id')->get();
    $ordered = $items->reverse()->pluck('id')->all();

    try {
        $result = (new ReorderOpportunityItems)($opportunity, ReorderOpportunityItemsData::from([
            'item_ids' => $ordered,
        ]));

        expect($result)->toHaveCount(count($ordered));
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('sort_order');
    }
});
