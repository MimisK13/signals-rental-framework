<?php

declare(strict_types=1);

use App\Domain\Administration\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Policies\ProjectPolicy;
use App\Domain\Projects\Presentation\Livewire\Index;
use App\Domain\Projects\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Gate;

it('uses the canonical project model across opportunity relationships and policy discovery', function (): void {
    $project = Project::factory()->create();
    $opportunity = Opportunity::factory()->create(['project_id' => $project->id]);

    expect($opportunity->project)->toBeInstanceOf(Project::class)
        ->and(Gate::getPolicyFor(Project::class))->toBeInstanceOf(ProjectPolicy::class);
});

it('serves native project presentation routes', function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $user = User::factory()->owner()->create();
    $project = Project::factory()->create(['name' => 'Native project']);

    $this->actingAs($user)->withoutVite()
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSeeLivewire(Index::class);

    $this->actingAs($user)->withoutVite()
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSeeLivewire(Show::class);
});

arch('Projects presentation does not depend on Volt', function (): void {
    expect('App\Domain\Projects')->not->toUse('Livewire\Volt');
});
