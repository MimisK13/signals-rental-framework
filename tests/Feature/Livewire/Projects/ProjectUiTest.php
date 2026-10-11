<?php

use App\Enums\ProjectStatus;
use App\Models\Member;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the project list and dense create panel contract', function (): void {
    Project::factory()->create(['name' => 'Summer Festival 2027']);

    Volt::test('projects.index')
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
    $component = Volt::test('projects.index')
        ->call('openCreateProject')
        ->set('projectName', 'Created from panel')
        ->call('saveProject');

    $component->assertHasNoErrors();

    expect(Project::query()->where('name', 'Created from panel')->exists())->toBeTrue()
        ->and($component->get('panel'))->toBeNull();
});

it('edits a project from the in-context side panel', function (): void {
    $project = Project::factory()->create(['name' => 'Before panel edit']);

    $component = Volt::test('projects.index')
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
    $component = Volt::test('projects.index')
        ->call('openCreateProject')
        ->set('projectName', '')
        ->call('saveProject');

    $component->assertHasErrors(['projectName' => 'required'])
        ->assertSet('panel', 'create');

    expect(Project::query()->count())->toBe(0);
});

it('rejects invalid member types through the panel and preserves create state', function (): void {
    $venue = Member::factory()->venue()->create();

    $component = Volt::test('projects.index')
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

    Volt::test('projects.index')
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

it('redirects the legacy create route to the create side panel', function (): void {
    $this->withoutMiddleware()
        ->get(route('projects.create'))
        ->assertRedirect(route('projects.index', ['panel' => 'create']));
});

it('redirects the legacy edit route to the matching edit side panel', function (): void {
    $project = Project::factory()->create();

    $this->withoutMiddleware()
        ->get(route('projects.edit', $project))
        ->assertRedirect(route('projects.index', ['panel' => 'edit', 'project' => $project->id]));
});

it('does not expose edit actions for archived projects', function (): void {
    $project = Project::factory()->create();
    $project->delete();

    Volt::test('projects.index')
        ->call('setArchiveFilter', 'all')
        ->assertDontSeeHtml('openEditProject('.$project->id.')');
});

it('applies active, archived, and all project scopes through the data table', function (): void {
    $activeProject = Project::factory()->create(['name' => 'Visible active project']);
    $archivedProject = Project::factory()->create(['name' => 'Visible archived project']);
    $archivedProject->delete();

    $component = Volt::test('projects.index')
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

    Volt::test('projects.index')
        ->call('setArchiveFilter', 'archived');

    $memberQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, ' from "members"'));

    expect($memberQueries)->toBeEmpty();
});

it('loads project panel options only once when the create panel opens', function (): void {
    DB::enableQueryLog();

    Volt::test('projects.index')
        ->call('openCreateProject');

    $memberQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, ' from "members"'));

    expect($memberQueries)->toHaveCount(3);
});

it('loads each project show dataset only once per render', function (): void {
    $project = Project::factory()->create();
    DB::enableQueryLog();

    Volt::test('projects.show', ['project' => $project]);

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $query): bool => str_contains($query, 'select * from "opportunities" where "opportunities"."project_id" ='))->count())->toBe(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'select * from "opportunities" where "project_id" is null'))->count())->toBe(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'from "members"'))->count())->toBeLessThanOrEqual(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'where "id" !='))->count())->toBe(1);
});
