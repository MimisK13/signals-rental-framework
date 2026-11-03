<?php

use App\Domain\Members\Models\Member;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Presentation\Livewire\Index;
use App\Domain\Projects\Presentation\Livewire\Show;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the project list and dense create panel contract', function (): void {
    Project::factory()->create(['name' => 'Summer Festival 2027']);

    Livewire::test(Index::class)
        ->assertSee('Summer Festival 2027')
        ->assertSee('New Project')
        ->assertSeeHtml('dense-side-panel')
        ->assertSeeHtml('project-panel-form')
        ->assertSeeHtml('dense-side-panel-footer-actions')
        ->assertSeeHtml('wire:name="components.data-table"')
        ->assertSeeHtml('role="menu"')
        ->assertDontSeeHtml('x-data="denseTabs');
});

it('creates a project from the in-context side panel', function (): void {
    $component = Livewire::test(Index::class)
        ->call('openCreateProject')
        ->set('projectName', 'Created from panel')
        ->call('saveProject');

    $component->assertHasNoErrors();

    expect(Project::query()->where('name', 'Created from panel')->exists())->toBeTrue()
        ->and($component->get('panel'))->toBeNull();
});

it('edits a project from the in-context side panel', function (): void {
    $project = Project::factory()->create(['name' => 'Before panel edit']);

    $component = Livewire::test(Index::class)
        ->call('openEditProject', $project->id)
        ->assertSet('projectName', 'Before panel edit')
        ->set('projectName', 'After panel edit')
        ->set('projectStatus', ProjectStatus::Active->value)
        ->call('saveProject');

    $component->assertHasNoErrors();

    expect($project->refresh()->name)->toBe('After panel edit')
        ->and($project->status)->toBe(ProjectStatus::Active);
});

it('keeps the panel usable after required-name validation fails', function (): void {
    $component = Livewire::test(Index::class)
        ->call('openCreateProject')
        ->set('projectName', '')
        ->call('saveProject');

    $component->assertHasErrors(['projectName' => 'required'])
        ->assertSet('panel', 'create');

    expect(Project::query()->count())->toBe(0);
});

it('rejects invalid member types through the panel and preserves create state', function (): void {
    $venue = Member::factory()->venue()->create();

    $component = Livewire::test(Index::class)
        ->call('openCreateProject')
        ->set('projectName', 'Invalid member project')
        ->set('projectCustomerId', $venue->id)
        ->call('saveProject');

    $component->assertHasErrors('projectCustomerId')
        ->assertSet('projectName', 'Invalid member project')
        ->assertSet('panel', 'create');

    expect(Project::query()->where('name', 'Invalid member project')->exists())->toBeFalse();
});

it('rejects an invalid lifecycle transition from the edit panel', function (): void {
    $project = Project::factory()->create(['status' => ProjectStatus::Draft]);

    Livewire::test(Index::class)
        ->call('openEditProject', $project->id)
        ->set('projectStatus', ProjectStatus::Completed->value)
        ->call('saveProject')
        ->assertHasErrors('projectStatus');

    expect($project->refresh()->status)->toBe(ProjectStatus::Draft);
});

it('denies an unauthorised user access to the project list', function (): void {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', Project::class))->toBeFalse();
});

it('opens the create side panel through the create route', function (): void {
    $this->withoutMiddleware()
        ->get(route('projects.create'))
        ->assertRedirect(route('projects.index', ['panel' => 'create']));
});

it('opens the matching edit side panel through the edit route', function (): void {
    $project = Project::factory()->create();

    $this->withoutMiddleware()
        ->get(route('projects.edit', $project))
        ->assertRedirect(route('projects.index', ['panel' => 'edit', 'project' => $project->id]));
});

it('does not expose edit actions for archived projects', function (): void {
    $project = Project::factory()->create();
    $project->delete();

    Livewire::test(Index::class)
        ->call('setArchiveFilter', 'all')
        ->assertDontSeeHtml('openEditProject('.$project->id.')');
});

it('applies active, archived, and all project scopes through the data table', function (): void {
    $activeProject = Project::factory()->create(['name' => 'Visible active project']);
    $archivedProject = Project::factory()->create(['name' => 'Visible archived project']);
    $archivedProject->delete();

    $component = Livewire::test(Index::class)
        ->assertSee('Visible active project')
        ->assertDontSee('Visible archived project')
        ->call('setArchiveFilter', 'archived')
        ->assertDontSee('Visible active project')
        ->assertSee('Visible archived project')
        ->call('setArchiveFilter', 'all')
        ->assertSee('Visible active project')
        ->assertSee('Visible archived project');

    expect($component->get('archiveFilter'))->toBe('all')
        ->and($activeProject->trashed())->toBeFalse();
});

it('does not load project panel options while switching archive filters', function (): void {
    DB::enableQueryLog();

    Livewire::test(Index::class)
        ->call('setArchiveFilter', 'archived');

    $memberQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, ' from "members"'));

    expect($memberQueries)->toBeEmpty();
});

it('loads project panel options only once when the create panel opens', function (): void {
    DB::enableQueryLog();

    Livewire::test(Index::class)
        ->call('openCreateProject');

    $memberQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, ' from "members"'));

    expect($memberQueries)->toHaveCount(3);
});

it('loads each project show dataset only once per render', function (): void {
    $project = Project::factory()->create(['member_id' => Member::factory()->organisation()]);
    Quotation::factory()->forProject($project)->create();
    DB::enableQueryLog();

    Livewire::test(Show::class, ['project' => $project]);

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $query): bool => str_contains($query, 'select * from "opportunities" where "opportunities"."project_id" ='))->count())->toBe(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'select * from "quotations" where "quotations"."project_id" ='))->count())->toBe(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'from "members"'))->count())->toBeLessThanOrEqual(1);
});

it('presents quotations as one part of the Project hub', function (): void {
    $project = Project::factory()->create([
        'name' => 'Project-first hub',
        'member_id' => Member::factory()->organisation(),
    ]);
    Quotation::factory()->forProject($project)->create([
        'number' => 'QUO-PROJECT-1',
        'subject' => 'Main production offer',
        'status' => QuotationStatus::Sent,
        'charge_total' => 125000,
    ]);

    Livewire::test(Show::class, ['project' => $project])
        ->assertSee('Quotations')
        ->assertSee('New Quotation')
        ->assertSee('QUO-PROJECT-1')
        ->assertSee('Main production offer')
        ->assertSee('Sent')
        ->assertSee('Jobs / Rentals')
        ->assertDontSee('Attach job')
        ->assertDontSee('Move job');
});
