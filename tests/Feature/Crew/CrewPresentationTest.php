<?php

declare(strict_types=1);

use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewQualification;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewSkill;
use App\Domain\Crew\Models\CrewTimeEntry;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Presentation\Livewire\Calendar;
use App\Domain\Crew\Presentation\Livewire\Configuration;
use App\Domain\Crew\Presentation\Livewire\Dashboard;
use App\Domain\Crew\Presentation\Livewire\Members\Form;
use App\Domain\Crew\Presentation\Livewire\Members\Index;
use App\Domain\Crew\Presentation\Livewire\Members\Show;
use App\Domain\Crew\Presentation\Livewire\Planner;
use App\Domain\Crew\Presentation\Livewire\Rates;
use App\Models\MemberStore;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true, 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    settings()->set('modules.crew', true, 'boolean');
});

/** @param list<string> $permissions */
function crewPresentationUser(array $permissions = []): User
{
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo($permissions === [] ? array_keys(PermissionSeeder::permissions()) : $permissions);

    return $user;
}

it('renders the crew operational pages with real records', function (string $page): void {
    $this->seed(PermissionSeeder::class);
    $user = crewPresentationUser();
    $this->actingAs($user);
    $parameters = match ($page) {
        Show::class,Form::class => ['crewMember' => CrewMember::factory()->create()],
        App\Domain\Crew\Presentation\Livewire\Requests\Show::class => ['crewRequest' => CrewRequest::factory()->create()],
        App\Domain\Crew\Presentation\Livewire\Assignments\Show::class => ['crewAssignment' => CrewAssignment::factory()->create()],
        App\Domain\Crew\Presentation\Livewire\Timesheets\Show::class => ['crewTimesheet' => CrewTimesheet::factory()->create()],
        default => [],
    };
    Livewire::test($page, $parameters)->assertOk()->assertSee('Crew');
})->with([Dashboard::class, Index::class, Form::class, Show::class, App\Domain\Crew\Presentation\Livewire\Requests\Index::class, App\Domain\Crew\Presentation\Livewire\Requests\Show::class, App\Domain\Crew\Presentation\Livewire\Assignments\Show::class, App\Domain\Crew\Presentation\Livewire\Timesheets\Index::class, App\Domain\Crew\Presentation\Livewire\Timesheets\Show::class, Planner::class, Calendar::class, Configuration::class, Rates::class]);

it('redirects unauthenticated crew requests to login', function (): void {
    $this->get(route('crew.members.index'))->assertRedirect(route('login'));
});

it('blocks the crew pages while the module is disabled', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    settings()->set('modules.crew', false, 'boolean');
    $this->get(route('crew.members.index'))->assertNotFound();
});

it('denies member lists without crew view permission', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser(['crew.access']));
    Livewire::test(Index::class)->assertForbidden();
});

it('creates a crew member through the member form', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    Livewire::test(Form::class)->set('name', 'Livewire Crew Member')->set('home_timezone', 'Europe/Athens')->call('save')->assertHasNoErrors()->assertRedirect();
    $this->assertDatabaseHas('crew_members', ['name' => 'Livewire Crew Member', 'home_timezone' => 'Europe/Athens', 'user_id' => null]);
    $this->assertDatabaseHas('action_logs', ['action' => 'crew.member.created']);
});

it('preserves identity and sensitive fields when editing ordinary member fields', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser(['crew.access', 'crew.view', 'crew.edit']));
    $member = CrewMember::factory()->create(['email' => 'private@example.test', 'internal_notes' => 'Private internal detail']);
    Livewire::test(Form::class, ['crewMember' => $member])->assertDontSee('private@example.test')->assertSet('email', null)->set('name', 'Updated Crew')->call('save')->assertHasNoErrors();
    expect($member->fresh()->email)->toBe('private@example.test');
    expect($member->fresh()->internal_notes)->toBe('Private internal detail');
});

it('keeps internal notes and agreed rates out of unauthorized rendered pages', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser(['crew.access', 'crew.view', 'crew.assignments.view']));
    $member = CrewMember::factory()->create(['internal_notes' => 'Secret crew note']);
    Livewire::test(Show::class, ['crewMember' => $member])->assertDontSee('Secret crew note');
    $assignment = CrewAssignment::factory()->create(['rate_amount_minor' => 87654321]);
    Livewire::test(App\Domain\Crew\Presentation\Livewire\Assignments\Show::class, ['crewAssignment' => $assignment])->assertDontSee('Agreed rate:')->assertDontSee('876543');
});

it('filters members by search without displaying unmatched records', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    CrewMember::factory()->create(['name' => 'Matching Sound Engineer', 'status' => 'active']);
    CrewMember::factory()->create(['name' => 'Unrelated Lighting Engineer', 'status' => 'active']);
    Livewire::test(Index::class)->set('search', 'Sound')->assertSee('Matching Sound Engineer')->assertDontSee('Unrelated Lighting Engineer');
});

it('rejects invalid member input without creating a record', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    Livewire::test(Form::class)->set('home_timezone', 'Invalid/Zone')->call('save')->assertHasErrors(['name', 'home_timezone']);
    $this->assertDatabaseCount('crew_members', 0);
});

it('renders entered names as text rather than executable markup', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    CrewMember::factory()->create(['name' => '<script>alert("crew")</script>', 'status' => 'active']);
    Livewire::test(Index::class)->assertDontSee('<script>alert("crew")</script>', false)->assertSee('&lt;script&gt;', false);
});

it('creates and edits each configuration type through the shared editor', function (string $kind, string $table): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $component = Livewire::test(Configuration::class, ['kind' => $kind])->set('name', 'Test taxonomy')->call('save')->assertHasNoErrors()->assertSee('Test taxonomy');
    $id = DB::table($table)->where('name', 'Test taxonomy')->value('id');
    $component->call('edit', $id)->set('name', 'Updated taxonomy')->call('save')->assertHasNoErrors()->assertSee('Updated taxonomy');
    $this->assertDatabaseHas($table, ['id' => $id, 'name' => 'Updated taxonomy']);
})->with([['roles', 'crew_roles'], ['skills', 'crew_skills'], ['qualifications', 'crew_qualifications']]);

it('uses the matching configuration type on each named route', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $this->withoutVite()->get(route('crew.skills.index'))->assertOk()->assertSee('Crew skills');
    $this->withoutVite()->get(route('crew.qualifications.index'))->assertOk()->assertSee('Crew qualifications');
});

it('creates a rate card from a decimal currency amount', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    Livewire::test(Rates::class)->set('hourly_rate', '12.34')->set('currency_code', 'GBP')->call('save')->assertHasNoErrors()->assertSee('12.34');
    $this->assertDatabaseHas('crew_rate_cards', ['hourly_rate_minor' => 1234, 'currency_code' => 'GBP']);
});

it('adds roles and skills and a verified qualification from member details', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $member = CrewMember::factory()->create();
    $role = CrewRole::factory()->create(['is_active' => true]);
    $skill = CrewSkill::factory()->create(['is_active' => true]);
    $qualification = CrewQualification::factory()->create(['is_active' => true]);
    $component = Livewire::test(Show::class, ['crewMember' => $member])
        ->set('role_id', $role->id)->call('addRole')->assertHasNoErrors()->assertSee($role->name)
        ->set('skill_id', $skill->id)->call('addSkill')->assertHasNoErrors()->assertSee($skill->name)
        ->set('crew_qualification_id', $qualification->id)->set('issued_at', today()->toDateString())->set('expires_at', today()->addYear()->toDateString())->call('addQualification')->assertHasNoErrors()->assertSee($qualification->name);
    $record = $member->qualifications()->firstOrFail();
    $component->call('verifyQualification', $record->id)->assertHasNoErrors()->assertSee('Valid');
    expect($record->fresh()->verified_by)->toBe(auth()->id());
});

it('adds a role line and publishes a crew request from its detail page', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $request = CrewRequest::factory()->draft()->create();
    $role = CrewRole::factory()->create(['is_active' => true]);
    Livewire::test(App\Domain\Crew\Presentation\Livewire\Requests\Show::class, ['crewRequest' => $request])->set('crew_role_id', $role->id)->set('quantity', 2)->call('addLine')->assertHasNoErrors()->assertSee($role->name)->call('publish')->assertHasNoErrors()->assertSee('Open');
    expect($request->fresh()->status->value)->toBe('open');
    $this->assertDatabaseHas('crew_request_lines', ['crew_request_id' => $request->id, 'quantity' => 2]);
});

it('adds actual time and completes the timesheet approval workflow through livewire', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $timesheet = CrewTimesheet::factory()->create(['period_start' => '2026-10-19', 'period_end' => '2026-10-25']);
    Livewire::test(App\Domain\Crew\Presentation\Livewire\Timesheets\Show::class, ['crewTimesheet' => $timesheet])
        ->set('started_at', '2026-10-20T09:00')->set('ended_at', '2026-10-20T17:00')->set('break_minutes', 30)
        ->call('addEntry')->assertHasNoErrors()->assertSee('450')
        ->call('submit')->assertHasNoErrors()->assertSee('Submitted')
        ->call('approve')->assertHasNoErrors()->assertSee('Approved')
        ->call('lock')->assertHasNoErrors()->assertSee('Locked');
    expect($timesheet->fresh()->status->value)->toBe('locked');
    expect($timesheet->fresh()->total_minutes)->toBe(450);
});

it('shows a stale timesheet error instead of overwriting another edit', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $timesheet = CrewTimesheet::factory()->create(['period_start' => '2026-10-19', 'period_end' => '2026-10-25']);
    $component = Livewire::test(App\Domain\Crew\Presentation\Livewire\Timesheets\Show::class, ['crewTimesheet' => $timesheet]);
    $timesheet->increment('version');
    $component->set('started_at', '2026-10-20T09:00')->set('ended_at', '2026-10-20T10:00')->call('addEntry')->assertHasErrors('version')->assertSee('Reload it before saving');
    expect($timesheet->entries()->count())->toBe(0);
});

it('edits and cancels a planned shift without losing its assignment', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $request = CrewRequest::factory()->open()->create(['starts_at' => '2026-11-10 08:00:00', 'ends_at' => '2026-11-10 20:00:00', 'timezone' => 'UTC']);
    $assignment = CrewAssignment::factory()->create(['crew_request_id' => $request->id]);
    $shift = CrewShift::factory()->forAssignment($assignment)->create(['starts_at' => '2026-11-10 09:00:00', 'ends_at' => '2026-11-10 17:00:00', 'location_name' => 'Stage A']);
    Livewire::test(App\Domain\Crew\Presentation\Livewire\Assignments\Show::class, ['crewAssignment' => $assignment])->call('editShift', $shift->id)->set('ends_at', '2026-11-10T16:00')->call('addShift')->assertHasNoErrors()->set('reason', 'Call changed')->call('cancelShift', $shift->id)->assertHasNoErrors()->assertSee('Cancelled');
    expect($shift->fresh()->ends_at->format('H:i'))->toBe('16:00');
    expect($shift->fresh()->location_name)->toBe('Stage A');
    expect($shift->fresh()->crew_assignment_id)->toBe($assignment->id);
});

it('edits actual time while retaining its shift reference and notes', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $timesheet = CrewTimesheet::factory()->create(['period_start' => '2026-10-19', 'period_end' => '2026-10-25']);
    $entry = CrewTimeEntry::factory()->for($timesheet, 'timesheet')->create(['started_at' => '2026-10-20 09:00:00', 'ended_at' => '2026-10-20 17:00:00', 'break_minutes' => 0, 'notes' => 'Retain this work detail']);
    Livewire::test(App\Domain\Crew\Presentation\Livewire\Timesheets\Show::class, ['crewTimesheet' => $timesheet])->call('editEntry', $entry->id)->set('ended_at', '2026-10-20T16:00')->call('addEntry')->assertHasNoErrors()->assertSee('420');
    expect($entry->fresh()->minutes)->toBe(420);
    expect($entry->fresh()->notes)->toBe('Retain this work detail');
    expect($timesheet->entries()->count())->toBe(1);
});

it('shows ranked candidates and their blocking reasons before proposing', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $request = CrewRequest::factory()->open()->create();
    $line = CrewRequestLine::factory()->for($request, 'request')->create();
    $member = CrewMember::factory()->create(['name' => 'Candidate Engineer', 'status' => 'active']);
    $member->roles()->attach($line->crew_role_id, ['proficiency' => 'expert', 'is_primary' => true]);
    Livewire::test(App\Domain\Crew\Presentation\Livewire\Requests\Show::class, ['crewRequest' => $request])->set('line_id', $line->id)->assertSee('Ranked candidates')->assertSee('Candidate Engineer')->assertSee('Score 80/100');
});

it('adds and removes role requirements through the configuration editor', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $role = CrewRole::factory()->create();
    $skill = CrewSkill::factory()->create(['is_active' => true]);
    $qualification = CrewQualification::factory()->create(['is_active' => true]);
    $component = Livewire::test(Configuration::class, ['kind' => 'roles'])->call('edit', $role->id)->set('requirement_skill_id', $skill->id)->call('addRequirement')->assertHasNoErrors()->assertSee($skill->name);
    $component->set('requirement_type', 'qualification')->set('requirement_qualification_id', $qualification->id)->call('addRequirement')->assertHasNoErrors()->assertSee($qualification->name);
    expect($role->requirements()->count())->toBe(2);
    $component->call('removeRequirement', $role->requirements()->where('crew_skill_id', $skill->id)->firstOrFail()->id)->assertHasNoErrors();
    expect($role->requirements()->count())->toBe(1);
});

it('rejects a nonexistent local time at the livewire boundary', function (): void {
    $this->seed(PermissionSeeder::class);
    $user = crewPresentationUser();
    $user->update(['timezone' => 'Europe/Athens']);
    $this->actingAs($user);
    $timesheet = CrewTimesheet::factory()->create(['period_start' => '2026-03-23', 'period_end' => '2026-03-29']);
    Livewire::test(App\Domain\Crew\Presentation\Livewire\Timesheets\Show::class, ['crewTimesheet' => $timesheet])->set('started_at', '2026-03-29T03:30')->set('ended_at', '2026-03-29T05:00')->call('addEntry')->assertHasErrors('started_at')->assertSee('This local time does not exist');
    expect($timesheet->entries()->count())->toBe(0);
});

it('reports invalid currency amounts in the rate form without saving', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    Livewire::test(Rates::class)->set('hourly_rate', '12.345')->set('currency_code', 'GBP')->call('save')->assertHasErrors('hourly_rate');
    $this->assertDatabaseCount('crew_rate_cards', 0);
});

it('hides a request from users without access to its store', function (): void {
    $this->seed(PermissionSeeder::class);
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $request = CrewRequest::factory()->forStore($otherStore)->create();
    $user = User::factory()->create(['is_admin' => false, 'is_owner' => false]);
    $user->givePermissionTo(['crew.access', 'crew.requests.view']);
    MemberStore::create(['member_id' => $user->member_id, 'store_id' => $store->id, 'created_at' => now()]);
    $this->actingAs($user);
    $this->withoutVite()->get(route('crew.requests.show', $request))->assertNotFound();
});

it('rechecks the module gate before a subsequent livewire mutation', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $component = Livewire::test(Form::class)->set('name', 'Module disabled member');
    settings()->set('modules.crew', false, 'boolean');
    $component->call('save')->assertNotFound();
    $this->assertDatabaseCount('crew_members', 0);
});

it('edits rate amounts while preserving compensation policy defaults', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $card = CrewRateCard::factory()->create(['hourly_rate_minor' => 2500, 'overtime_multiplier' => '1.75', 'notes' => 'Keep agreed policy']);
    Livewire::test(Rates::class)->call('edit', $card->id)->set('hourly_rate', '27.50')->call('save')->assertHasNoErrors()->assertSee('27.50');
    expect($card->fresh()->hourly_rate_minor)->toBe(2750);
    expect($card->fresh()->overtime_multiplier)->toBe('1.75');
    expect($card->fresh()->notes)->toBe('Keep agreed policy');
});

it('substitutes an assignment through livewire while preserving the original agreed compensation', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $request = CrewRequest::factory()->filled()->create(['starts_at' => '2026-11-10 08:00:00', 'ends_at' => '2026-11-10 20:00:00', 'timezone' => 'UTC']);
    $original = CrewAssignment::factory()->confirmed()->create(['crew_request_id' => $request->id, 'rate_amount_minor' => 4500, 'min_call_hours' => '2.00']);
    $shift = CrewShift::factory()->forAssignment($original)->confirmed()->create(['starts_at' => '2026-11-10 09:00:00', 'ends_at' => '2026-11-10 17:00:00']);
    $replacementMember = CrewMember::factory()->create(['status' => 'active']);
    $replacementMember->roles()->attach($original->line->crew_role_id, ['proficiency' => 'expert', 'is_primary' => true]);
    CrewRateCard::factory()->create(['crew_member_id' => $replacementMember->id, 'hourly_rate_minor' => 6000, 'effective_from' => '2020-01-01']);
    $agreed = $original->only(['rate_amount_minor', 'currency_code', 'overtime_multiplier', 'min_call_hours', 'agreed_at', 'agreed_by', 'confirmed_at']);

    $component = Livewire::test(App\Domain\Crew\Presentation\Livewire\Assignments\Show::class, ['crewAssignment' => $original])
        ->set('replacement_member_id', $replacementMember->id)
        ->set('substitution_reason', 'Original engineer is unavailable')
        ->call('substitute')
        ->assertHasNoErrors();
    $replacement = CrewAssignment::where('substitute_for_assignment_id', $original->id)->firstOrFail();
    $component->assertRedirect(route('crew.assignments.show', $replacement));

    expect($original->fresh()->status->value)->toBe('substituted');
    expect($original->fresh()->only(array_keys($agreed)))->toEqual($agreed);
    expect($replacement->crew_member_id)->toBe($replacementMember->id);
    expect($replacement->rate_amount_minor)->toBe(6000);
    expect($replacement->status->value)->toBe('confirmed');
    expect($shift->fresh()->status->value)->toBe('cancelled');
    expect($replacement->shifts()->count())->toBe(1);
    $this->assertDatabaseHas('action_logs', ['action' => 'crew.assignment.substituted']);
});

it('denies livewire substitution without the substitution permission', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser(['crew.access', 'crew.assignments.view']));
    $assignment = CrewAssignment::factory()->confirmed()->create();

    Livewire::test(App\Domain\Crew\Presentation\Livewire\Assignments\Show::class, ['crewAssignment' => $assignment])
        ->assertDontSee('Substitute crew member')
        ->call('substitute')
        ->assertForbidden();

    expect($assignment->fresh()->status->value)->toBe('confirmed');
    expect(CrewAssignment::where('substitute_for_assignment_id', $assignment->id)->count())->toBe(0);
});

it('realigns explicitly reviewed shift dates and clears the schedule impact flag without changing agreed terms', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser());
    $request = CrewRequest::factory()->open()->create(['starts_at' => '2026-11-10 08:00:00', 'ends_at' => '2026-11-10 20:00:00', 'timezone' => 'UTC']);
    $assignment = CrewAssignment::factory()->confirmed()->create(['crew_request_id' => $request->id, 'requires_realignment' => true, 'realignment_flagged_at' => now(), 'realignment_reason' => 'Project schedule changed', 'rate_amount_minor' => 4500]);
    $assignment->crewMember->roles()->attach($assignment->line->crew_role_id, ['proficiency' => 'competent']);
    $shift = CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2026-11-10 09:00:00', 'ends_at' => '2026-11-10 17:00:00']);
    $agreed = $assignment->only(['rate_amount_minor', 'currency_code', 'overtime_multiplier', 'agreed_at', 'agreed_by']);

    $component = Livewire::test(App\Domain\Crew\Presentation\Livewire\Assignments\Show::class, ['crewAssignment' => $assignment])->call('beginRealignment')->assertSet('realigning', true);
    expect($shift->fresh()->starts_at->format('H:i'))->toBe('09:00');
    $component->set('realignmentShifts.0.starts_at', '2026-11-10T10:00')
        ->set('realignmentShifts.0.ends_at', '2026-11-10T18:00')
        ->set('realignment_reason', 'Reviewed the new production call')
        ->call('realign')
        ->assertHasNoErrors()
        ->assertSet('realigning', false)
        ->assertDontSee('Project dates changed. Review and explicitly realign');

    expect($assignment->fresh()->requires_realignment)->toBeFalse();
    expect($shift->fresh()->starts_at->format('H:i'))->toBe('10:00');
    expect($shift->fresh()->ends_at->format('H:i'))->toBe('18:00');
    expect($shift->fresh()->status->value)->toBe('confirmed');
    expect($assignment->fresh()->only(array_keys($agreed)))->toEqual($agreed);
    $this->assertDatabaseHas('action_logs', ['action' => 'crew.assignment.realigned']);
});

it('denies livewire schedule realignment without shift management access', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(crewPresentationUser(['crew.access', 'crew.assignments.view']));
    $assignment = CrewAssignment::factory()->confirmed()->create(['requires_realignment' => true]);

    Livewire::test(App\Domain\Crew\Presentation\Livewire\Assignments\Show::class, ['crewAssignment' => $assignment])
        ->assertDontSee('Review shift realignment')
        ->call('realign')
        ->assertForbidden();

    expect($assignment->fresh()->requires_realignment)->toBeTrue();
});
