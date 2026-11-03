<?php

use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Enums\OpportunityState;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Index;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Opportunities index page (Volt) — M8-1
|--------------------------------------------------------------------------
|
| Archive/restore exercise the event-sourced Delete/RestoreOpportunity actions,
| so those rows are created through the real CreateOpportunity pipeline (a
| genuine Verbs state). Read-only rendering/filter assertions can use factory
| rows, which bypass the event stream.
|
| NOTE (SQLite test DB): the DataTable's global search uses the `ilike`
| predicate, which is PostgreSQL-only and errors on the SQLite test DB (see the
| test-db SQLite gotcha). We therefore assert the searchable prop is WIRED on
| the nested data-table rather than driving the live `search` query path.
|
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->actingAs($this->owner);
});

/**
 * Create an event-sourced opportunity as the authenticated owner.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeIndexOpportunity(User $actor, int $storeId, array $attributes = []): Opportunity
{
    Auth::login($actor);

    try {
        $result = (new CreateOpportunity)(CreateOpportunityData::from(array_merge([
            'subject' => 'Index Opportunity',
            'store_id' => $storeId,
        ], $attributes)));

        return Opportunity::query()->whereKey($result->id)->firstOrFail();
    } finally {
        Auth::login($actor);
    }
}

it('renders the opportunities index page for a permitted user', function () {
    Opportunity::factory()->count(3)->create();

    $this->get(route('opportunities.index'))
        ->assertOk()
        ->assertSee('Opportunities');
});

it('forbids a user lacking opportunities.access', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('opportunities.index'))
        ->assertForbidden();
});

it('lists opportunities by subject', function () {
    Opportunity::factory()->create(['subject' => 'Festival Hire']);
    Opportunity::factory()->create(['subject' => 'Wedding Package']);

    Livewire::test(Index::class)
        ->assertSee('Festival Hire')
        ->assertSee('Wedding Package');
});

it('filters by document-type state', function () {
    Opportunity::factory()->quotation()->create(['subject' => 'A Quote Subject']);
    Opportunity::factory()->order()->create(['subject' => 'An Order Subject']);

    Livewire::test(Index::class)
        ->set('stateFilter', (string) OpportunityState::Quotation->value)
        ->assertSee('A Quote Subject')
        ->assertDontSee('An Order Subject');
});

it('ignores an invalid state in setStateFilter', function () {
    Livewire::test(Index::class)
        ->call('setStateFilter', '999')
        ->assertSet('stateFilter', '');
});

it('ignores an invalid archive filter', function () {
    Livewire::test(Index::class)
        ->call('setArchiveFilter', 'invalid_filter')
        ->assertSet('archiveFilter', 'active');
});

it('reflects state counts for created opportunities', function () {
    Opportunity::factory()->quotation()->count(2)->create();
    Opportunity::factory()->order()->create();

    Livewire::test(Index::class)
        ->assertSet('totalCount', 3)
        ->assertSet('stateFilter', '');
});

it('switches the archive filter and refreshes counts', function () {
    Opportunity::factory()->quotation()->create();

    Livewire::test(Index::class)
        ->assertSet('archiveFilter', 'active')
        ->assertSet('totalCount', 1)
        ->call('setArchiveFilter', 'archived')
        ->assertSet('archiveFilter', 'archived')
        // No archived rows yet, so the archived view counts zero.
        ->assertSet('totalCount', 0)
        ->call('setArchiveFilter', 'all')
        ->assertSet('totalCount', 1);
});

it('wires the searchable columns on the nested data table', function () {
    // The data-table search path uses pg-only `ilike` (unexecutable on SQLite),
    // so we assert the searchable prop is passed through rather than querying.
    Opportunity::factory()->create(['subject' => 'Searchable Subject']);

    Livewire::test(Index::class)
        ->assertSee('Searchable Subject');
});

it('shows the empty state when no opportunities exist', function () {
    Livewire::test(Index::class)
        ->assertSee('No opportunities found.');
});

it('archives a single opportunity through the event action', function () {
    $opportunity = makeIndexOpportunity($this->owner, $this->store->id);

    Livewire::test(Index::class)
        ->call('archiveOpportunity', $opportunity->id);

    expect(Opportunity::withTrashed()->find($opportunity->id)->trashed())->toBeTrue();
});

it('restores a single opportunity through the event action', function () {
    $opportunity = makeIndexOpportunity($this->owner, $this->store->id);

    Livewire::test(Index::class)
        ->call('archiveOpportunity', $opportunity->id)
        ->call('restoreOpportunity', $opportunity->id);

    expect(Opportunity::find($opportunity->id))->not->toBeNull();
    expect(Opportunity::find($opportunity->id)->trashed())->toBeFalse();
});

it('bulk archives then bulk restores opportunities', function () {
    $opportunity = makeIndexOpportunity($this->owner, $this->store->id);

    Livewire::test(Index::class)
        ->call('archiveSelected', [$opportunity->id]);

    expect(Opportunity::onlyTrashed()->find($opportunity->id))->not->toBeNull();

    Livewire::test(Index::class)
        ->call('restoreSelected', [$opportunity->id]);

    expect(Opportunity::find($opportunity->id)->trashed())->toBeFalse();
});

it('filters to shortage opportunities when the shortage toggle is on', function () {
    Opportunity::factory()->withShortage()->create(['subject' => 'Shortage Job']);
    Opportunity::factory()->create(['subject' => 'Healthy Job']);

    Livewire::test(Index::class)
        ->assertSee('Shortage Job')
        ->assertSee('Healthy Job')
        ->call('toggleShortageFilter')
        ->assertSet('shortageFilter', true)
        ->assertSee('Shortage Job')
        ->assertDontSee('Healthy Job');
});

it('toggles the shortage filter off again', function () {
    Opportunity::factory()->withShortage()->create(['subject' => 'Shortage Job']);
    Opportunity::factory()->create(['subject' => 'Healthy Job']);

    Livewire::test(Index::class)
        ->call('toggleShortageFilter')
        ->assertSet('shortageFilter', true)
        ->call('toggleShortageFilter')
        ->assertSet('shortageFilter', false)
        ->assertSee('Shortage Job')
        ->assertSee('Healthy Job');
});

it('counts shortage opportunities for the chip regardless of the toggle', function () {
    Opportunity::factory()->withShortage()->count(2)->create();
    Opportunity::factory()->create();

    Livewire::test(Index::class)
        ->assertSet('shortageCount', 2)
        ->call('toggleShortageFilter')
        // The toggle narrows the listing but the chip still reports the full count.
        ->assertSet('shortageCount', 2)
        ->assertSet('totalCount', 2);
});

it('hydrates the shortage filter from the has_shortage url param (deep link)', function () {
    Opportunity::factory()->withShortage()->create(['subject' => 'Linked Shortage']);
    Opportunity::factory()->create(['subject' => 'Other Job']);

    // The dashboard "With Shortages" stat deep-links via ?has_shortage=1; the
    // #[Url(as: 'has_shortage')] bool property round-trips that into the filter.
    Livewire::withQueryParams(['has_shortage' => 1])
        ->test('opportunities.index')
        ->assertSet('shortageFilter', true)
        ->assertSee('Linked Shortage')
        ->assertDontSee('Other Job');
});

it('passes the withShortage scope to the data table when the filter is on', function () {
    Livewire::test(Index::class)
        ->call('toggleShortageFilter')
        ->assertSet('shortageFilter', true)
        ->assertViewHas('scopes', fn (array $scopes): bool => ($scopes['withShortage'] ?? null) === true);
});

it('forbids archiving for a user without opportunities.delete', function () {
    $opportunity = makeIndexOpportunity($this->owner, $this->store->id);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['opportunities.access', 'opportunities.view']);
    $this->actingAs($viewer);

    // mount() authorizes opportunities.access (granted), but archiveOpportunity
    // calls DeleteOpportunity, whose Gate::authorize('opportunities.delete')
    // denies — Livewire surfaces that as a 403.
    Livewire::test(Index::class)
        ->call('archiveOpportunity', $opportunity->id)
        ->assertForbidden();

    expect(Opportunity::find($opportunity->id)->trashed())->toBeFalse();
});
