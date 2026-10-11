<?php

use App\Actions\Projects\AttachOpportunityToProject;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\DeleteProject;
use App\Actions\Projects\DetachOpportunityFromProject;
use App\Actions\Projects\MoveOpportunityToProject;
use App\Actions\Projects\UpdateProject;
use App\Data\Projects\CreateProjectData;
use App\Data\Projects\UpdateProjectData;
use App\Enums\ProjectStatus;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Store;
use App\Models\User;
use App\Verbs\Events\Opportunities\OpportunityProjectAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Thunk\Verbs\Models\VerbEvent;

beforeEach(function (): void {
    $this->actingAs(User::factory()->owner()->create());
});

it('stores project metadata without an inventory location and casts its status', function (): void {
    $customer = Member::factory()->organisation()->create();
    $venue = Member::factory()->venue()->create();
    $owner = Member::factory()->user()->create();

    $project = Project::factory()->create([
        'member_id' => $customer->id,
        'venue_id' => $venue->id,
        'owned_by' => $owner->id,
        'status' => ProjectStatus::Active,
    ]);

    expect($project->status)->toBe(ProjectStatus::Active)
        ->and($project->customer->is($customer))->toBeTrue()
        ->and($project->venue->is($venue))->toBeTrue()
        ->and($project->owner->is($owner))->toBeTrue()
        ->and(array_key_exists('store_id', $project->getAttributes()))->toBeFalse();
});

it('can clear nullable project metadata without changing omitted fields', function (): void {
    $customer = Member::factory()->organisation()->create();
    $venue = Member::factory()->venue()->create();
    $project = Project::factory()->create([
        'member_id' => $customer->id,
        'venue_id' => $venue->id,
    ]);

    (new UpdateProject)($project, UpdateProjectData::from(['venue_id' => null]));

    expect($project->refresh()->member_id)->toBe($customer->id)
        ->and($project->venue_id)->toBeNull();
});

it('rejects invalid customer, venue, and owner member types in the action boundary', function (): void {
    $organisation = Member::factory()->organisation()->create();
    $venue = Member::factory()->venue()->create();
    $user = Member::factory()->user()->create();

    expect(fn () => (new CreateProject)(CreateProjectData::from([
        'name' => 'Invalid customer',
        'member_id' => $venue->id,
    ])))->toThrow(ValidationException::class)
        ->and(fn () => (new CreateProject)(CreateProjectData::from([
            'name' => 'Invalid venue',
            'member_id' => $organisation->id,
            'venue_id' => $organisation->id,
        ])))->toThrow(ValidationException::class)
        ->and(fn () => (new CreateProject)(CreateProjectData::from([
            'name' => 'Invalid owner',
            'member_id' => $organisation->id,
            'owned_by' => $venue->id,
        ])))->toThrow(ValidationException::class)
        ->and($user->membership_type->value)->toBe('user');
});

it('enforces the project lifecycle transition matrix', function (): void {
    expect(ProjectStatus::Draft->canTransitionTo(ProjectStatus::Active))->toBeTrue()
        ->and(ProjectStatus::Draft->canTransitionTo(ProjectStatus::Completed))->toBeFalse()
        ->and(ProjectStatus::Draft->canTransitionTo(ProjectStatus::Archived))->toBeTrue()
        ->and(ProjectStatus::Active->canTransitionTo(ProjectStatus::Draft))->toBeFalse()
        ->and(ProjectStatus::Active->canTransitionTo(ProjectStatus::Completed))->toBeTrue()
        ->and(ProjectStatus::Active->canTransitionTo(ProjectStatus::Archived))->toBeTrue()
        ->and(ProjectStatus::Completed->canTransitionTo(ProjectStatus::Active))->toBeFalse()
        ->and(ProjectStatus::Completed->canTransitionTo(ProjectStatus::Archived))->toBeTrue()
        ->and(ProjectStatus::Archived->canTransitionTo(ProjectStatus::Draft))->toBeFalse();

    $project = Project::factory()->create(['status' => ProjectStatus::Draft]);

    expect(fn () => (new UpdateProject)($project, UpdateProjectData::from([
        'status' => ProjectStatus::Completed->value,
    ])))->toThrow(ValidationException::class);
});

it('evaluates the project pagination count once per render', function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    Project::factory()->create(['name' => 'Query-count project']);
    $countQueries = [];

    DB::listen(function ($query) use (&$countQueries): void {
        $sql = strtolower($query->sql);

        if (str_starts_with($sql, 'select count(*) as "aggregate" from "projects"')) {
            $countQueries[] = $query->sql;
        }
    });

    Volt::test('projects.index')->assertSee('Query-count project');

    expect($countQueries)->toHaveCount(1);
});

it('attaches, moves, and detaches an opportunity without changing its store', function (): void {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    $opportunity = Opportunity::factory()->create(['store_id' => $storeA->id]);

    (new AttachOpportunityToProject)($projectA, $opportunity);
    $opportunity->refresh();

    expect($opportunity->project_id)->toBe($projectA->id)
        ->and($opportunity->store_id)->toBe($storeA->id);

    (new MoveOpportunityToProject)($projectB, $opportunity);
    $opportunity->refresh();

    expect($opportunity->project_id)->toBe($projectB->id)
        ->and($opportunity->store_id)->toBe($storeA->id);

    (new DetachOpportunityFromProject)($projectB, $opportunity);
    $opportunity->refresh();

    expect($opportunity->project_id)->toBeNull()
        ->and($opportunity->store_id)->toBe($storeA->id);

    $secondOpportunity = Opportunity::factory()->create(['store_id' => $storeB->id]);
    (new AttachOpportunityToProject)($projectA, $secondOpportunity);

    expect($projectA->refresh()->opportunities)->toHaveCount(1);
});

it('does not silently attach an opportunity that belongs to another project', function (): void {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    $opportunity = Opportunity::factory()->create();

    (new AttachOpportunityToProject)($projectA, $opportunity);

    expect(fn () => (new AttachOpportunityToProject)($projectB, $opportunity->refresh()))
        ->toThrow(ValidationException::class);

    expect($opportunity->refresh()->project_id)->toBe($projectA->id);
});

it('deletes a project without deleting its opportunities and clears their links', function (): void {
    $project = Project::factory()->create();
    $opportunity = Opportunity::factory()->create();

    (new AttachOpportunityToProject)($project, $opportunity);
    (new DeleteProject)($project);

    expect($project->fresh()->trashed())->toBeTrue()
        ->and(Opportunity::query()->findOrFail($opportunity->id)->project_id)->toBeNull()
        ->and(Opportunity::query()->findOrFail($opportunity->id)->exists)->toBeTrue();
});

it('records attach, move, detach, and project deletion through the Opportunity event path', function (): void {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $eventType = OpportunityProjectAssigned::class;

    (new AttachOpportunityToProject)($projectA, $opportunity);
    (new MoveOpportunityToProject)($projectB, $opportunity->refresh());
    (new DetachOpportunityFromProject)($projectB, $opportunity->refresh());
    (new AttachOpportunityToProject)($projectA, $opportunity->refresh());
    (new DeleteProject)($projectA);

    expect(VerbEvent::query()->type($eventType)->count())->toBe(5)
        ->and($opportunity->refresh()->project_id)->toBeNull();
});
