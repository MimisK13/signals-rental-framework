<?php

declare(strict_types=1);

use App\Domain\Crew\Actions\Assignments\CancelCrewAssignment;
use App\Domain\Crew\Actions\Assignments\ConfirmCrewAssignment;
use App\Domain\Crew\Actions\Assignments\InviteCrewAssignment;
use App\Domain\Crew\Actions\Assignments\ProposeCrewAssignment;
use App\Domain\Crew\Actions\Assignments\SubstituteCrewAssignment;
use App\Domain\Crew\Actions\Availability\ApproveCrewAvailability;
use App\Domain\Crew\Actions\Availability\RequestCrewAvailability;
use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Actions\Requests\AddCrewRequestLine;
use App\Domain\Crew\Actions\Requests\CancelCrewRequest;
use App\Domain\Crew\Actions\Requests\CreateCrewRequest;
use App\Domain\Crew\Actions\Requests\PublishCrewRequest;
use App\Domain\Crew\Actions\Requests\SyncCrewRequestLineRequirements;
use App\Domain\Crew\Actions\Shifts\CancelCrewShift;
use App\Domain\Crew\Actions\Shifts\ConfirmCrewShift;
use App\Domain\Crew\Actions\Shifts\CreateCrewShift;
use App\Domain\Crew\Actions\Shifts\RealignAssignmentShifts;
use App\Domain\Crew\Actions\Shifts\UpdateCrewShift;
use App\Domain\Crew\Data\Assignments\CancelCrewAssignmentData;
use App\Domain\Crew\Data\Assignments\ProposeCrewAssignmentData;
use App\Domain\Crew\Data\Assignments\SubstituteCrewAssignmentData;
use App\Domain\Crew\Data\Availability\RequestCrewAvailabilityData;
use App\Domain\Crew\Data\Planning\MatchCrewCandidatesData;
use App\Domain\Crew\Data\Requests\AddCrewRequestLineData;
use App\Domain\Crew\Data\Requests\CancelCrewRequestData;
use App\Domain\Crew\Data\Requests\CreateCrewRequestData;
use App\Domain\Crew\Data\Requests\SyncLineRequirementsData;
use App\Domain\Crew\Data\Shifts\CancelCrewShiftData;
use App\Domain\Crew\Data\Shifts\CreateCrewShiftData;
use App\Domain\Crew\Data\Shifts\RealignAssignmentShiftsData;
use App\Domain\Crew\Data\Shifts\UpdateCrewShiftData;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewSkill;
use App\Domain\Crew\Observers\CrewProjectScheduleObserver;
use App\Domain\Crew\Queries\CrewAvailabilityQuery;
use App\Domain\Crew\Queries\CrewCandidateQuery;
use App\Domain\Crew\Queries\CrewConflictQuery;
use App\Domain\Crew\Queries\CrewCoverageQuery;
use App\Domain\Currency\Models\ExchangeRate;
use App\Models\ActionLog;
use App\Models\MemberStore;
use App\Models\Project;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @return array{CrewRequest,CrewRequestLine,CrewMember,CrewRateCard} */
function crewStaffingFixture(): array
{
    \Pest\Laravel\freezeTime();
    \Pest\Laravel\seed(PermissionSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo(array_keys(array_filter(PermissionSeeder::permissions(), static function (array $meta): bool {
        return $meta['group'] === 'Crew';
    })));
    \Pest\Laravel\actingAs($user);
    $request = CrewRequest::factory()->open()->create(['starts_at' => '2027-02-10 00:00:00', 'ends_at' => '2027-02-12 23:59:00']);
    $line = CrewRequestLine::factory()->create(['crew_request_id' => $request->id, 'starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 17:00:00']);
    $member = CrewMember::factory()->create(['starts_on' => null, 'ends_on' => null]);
    $member->roles()->attach($line->crew_role_id, ['proficiency' => 'competent', 'is_primary' => true]);
    $rate = CrewRateCard::factory()->forMember($member)->create(['effective_from' => '2027-01-01']);

    return [$request, $line, $member, $rate];
}

it('creates a draft request and publishes it only after adding a role line', function (): void {
    [$existing] = crewStaffingFixture();
    $project = Project::factory()->create();
    $dto = app(CreateCrewRequest::class)(new CreateCrewRequestData('Show crew', $project->id, $existing->store_id, '2027-02-10 00:00:00', '2027-02-12 00:00:00'));
    $request = CrewRequest::findOrFail($dto->id);
    expect(fn () => app(PublishCrewRequest::class)($request))->toThrow(ValidationException::class);
    $role = CrewRole::factory()->create();
    app(AddCrewRequestLine::class)($request, new AddCrewRequestLineData($role->id, 2, '2027-02-10 09:00:00', '2027-02-10 17:00:00'));

    $published = app(PublishCrewRequest::class)($request);

    expect($published->status)->toBe('open');
    \Pest\Laravel\assertDatabaseHas('action_logs', ['action' => 'crew.request.published', 'auditable_id' => $request->id]);
});

it('confirms invited assignments and updates real coverage without requiring a login for the member', function (): void {
    [$request, $line, $member] = crewStaffingFixture();
    $member->update(['user_id' => null]);
    $proposed = app(ProposeCrewAssignment::class)($line, new ProposeCrewAssignmentData($member->id));
    $assignment = CrewAssignment::findOrFail($proposed->id);
    app(InviteCrewAssignment::class)($assignment);

    $confirmed = app(ConfirmCrewAssignment::class)($assignment->refresh());

    expect($confirmed->status)->toBe('confirmed');
    expect($request->refresh()->status)->toBe(CrewRequestStatus::Filled);
    expect($line->refresh()->filled_quantity)->toBe(1);
    expect(app(CrewCoverageQuery::class)->forRequest($request)->lines[0]->shortage)->toBe(0);
    \Pest\Laravel\assertDatabaseHas('action_logs', ['action' => 'crew.assignment.confirmed', 'auditable_id' => $assignment->id]);
});

it('rejects duplicate active assignments before creating a second proposal', function (): void {
    [, $line, $member] = crewStaffingFixture();
    app(ProposeCrewAssignment::class)($line, new ProposeCrewAssignmentData($member->id));

    expect(fn () => app(ProposeCrewAssignment::class)($line, new ProposeCrewAssignmentData($member->id)))->toThrow(ValidationException::class, 'already has an active assignment');
    expect($line->assignments()->count())->toBe(1);
});

it('rechecks approved leave when confirming a previously invited assignment', function (): void {
    [, $line, $member] = crewStaffingFixture();
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->invited()->create();
    CrewAvailability::factory()->create(['crew_member_id' => $member->id, 'status' => 'approved', 'starts_at' => $line->starts_at, 'ends_at' => $line->ends_at]);

    expect(fn () => app(ConfirmCrewAssignment::class)($assignment, 'Cannot override hard leave'))->toThrow(ValidationException::class, 'approved unavailability');
    expect($assignment->refresh()->status)->toBe(CrewAssignmentStatus::Invited);
});

it('blocks confirmation when another invitation fills the final slot first', function (): void {
    [, $line, $member] = crewStaffingFixture();
    $first = CrewAssignment::factory()->forLine($line)->forMember($member)->invited()->create();
    $other = CrewMember::factory()->create(['starts_on' => null, 'ends_on' => null]);
    $other->roles()->attach($line->crew_role_id, ['proficiency' => 'competent']);
    $second = CrewAssignment::factory()->forLine($line)->forMember($other)->invited()->create();
    app(ConfirmCrewAssignment::class)($first);

    expect(fn () => app(ConfirmCrewAssignment::class)($second))->toThrow(ValidationException::class);
    expect($line->refresh()->filled_quantity)->toBe(1);
});

it('keeps hard conflicts visible across stores without exposing another project', function (): void {
    [, $line, $member] = crewStaffingFixture();
    $otherStore = Store::factory()->create();
    CrewShift::factory()->confirmed()->create(['crew_member_id' => $member->id, 'store_id' => $otherStore->id, 'starts_at' => $line->starts_at, 'ends_at' => $line->ends_at]);

    $report = app(CrewConflictQuery::class)->detect($member->id, CarbonImmutable::parse($line->starts_at), CarbonImmutable::parse($line->ends_at));

    expect($report->hard[0])->toBe(['code' => 'SHIFT_OVERLAP_CONFIRMED', 'message' => 'The crew member has another shift during this period.']);
});

it('treats touching intervals as a rest violation rather than an overlap', function (): void {
    [, , $member] = crewStaffingFixture();
    CrewShift::factory()->confirmed()->create(['crew_member_id' => $member->id, 'starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 13:00:00']);

    $report = app(CrewConflictQuery::class)->detect($member->id, CarbonImmutable::parse('2027-02-10 13:00:00'), CarbonImmutable::parse('2027-02-10 17:00:00'));

    expect(array_column($report->hard, 'code'))->toBe(['REST_BELOW_MINIMUM']);
    expect($report->warnings)->toBe([]);
});

it('requires a reason and permission before acknowledging requested leave', function (): void {
    [, $line, $member] = crewStaffingFixture();
    CrewAvailability::factory()->create(['crew_member_id' => $member->id, 'status' => 'requested', 'starts_at' => $line->starts_at, 'ends_at' => $line->ends_at]);

    expect(fn () => app(ProposeCrewAssignment::class)($line, new ProposeCrewAssignmentData($member->id)))->toThrow(ValidationException::class, 'reason is required');
    $result = app(ProposeCrewAssignment::class)($line, new ProposeCrewAssignmentData($member->id), 'Planner discussed pending leave');

    expect($result->status)->toBe('proposed');
    \Pest\Laravel\assertDatabaseHas('action_logs', ['action' => 'crew.assignment.proposed', 'auditable_id' => $result->id]);
});

it('blocks missing mandatory skills even with a scheduling override', function (): void {
    [, $line, $member] = crewStaffingFixture();
    $skill = CrewSkill::factory()->create();
    $line->requirements()->create(['requirement_type' => 'skill', 'crew_skill_id' => $skill->id, 'is_mandatory' => true]);

    expect(fn () => app(ProposeCrewAssignment::class)($line, new ProposeCrewAssignmentData($member->id), 'Override'))->toThrow(ValidationException::class, 'required skill is missing');
});

it('reopens coverage and releases active keys when cancelling an assignment', function (): void {
    [$request, $line, $member] = crewStaffingFixture();
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->invited()->create();
    app(ConfirmCrewAssignment::class)($assignment);
    $shift = CrewShift::factory()->forAssignment($assignment)->planned()->create();

    app(CancelCrewAssignment::class)($assignment->refresh(), new CancelCrewAssignmentData('Project scope changed'));

    expect($assignment->refresh()->active_assignment_key)->toBeNull();
    expect($request->refresh()->status)->toBe(CrewRequestStatus::Open);
    expect($line->refresh()->filled_quantity)->toBe(0);
    expect($shift->refresh()->status->value)->toBe('cancelled');
});

it('cancels pending work when its request is cancelled', function (): void {
    [$request, $line, $member] = crewStaffingFixture();
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->invited()->create();

    app(CancelCrewRequest::class)($request, new CancelCrewRequestData('Event cancelled'));

    expect($request->refresh()->status)->toBe(CrewRequestStatus::Cancelled);
    expect($assignment->refresh()->status)->toBe(CrewAssignmentStatus::Cancelled);
    expect($assignment->active_assignment_key)->toBeNull();
});

it('derives overnight shifts from UTC instants and refuses excessive breaks', function (): void {
    [, $line, $member] = crewStaffingFixture();
    $line->update(['starts_at' => '2027-02-10 20:00:00', 'ends_at' => '2027-02-11 08:00:00']);
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->invited()->create();

    $shift = app(CreateCrewShift::class)($assignment, new CreateCrewShiftData('2027-02-10 22:00:00', '2027-02-11 06:00:00', 'Europe/Athens', break_minutes: 30));

    expect($shift->is_overnight)->toBeTrue();
    expect(fn () => app(CreateCrewShift::class)($assignment, new CreateCrewShiftData('2027-02-11 06:00:00', '2027-02-11 07:00:00', break_minutes: 60)))->toThrow(ValidationException::class, 'Breaks must be shorter');
});

it('requires a confirmed assignment before confirming a planned shift', function (): void {
    [, $line, $member] = crewStaffingFixture();
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->invited()->create();
    $shift = CrewShift::factory()->forAssignment($assignment)->create(['starts_at' => $line->starts_at, 'ends_at' => $line->ends_at]);

    expect(fn () => app(ConfirmCrewShift::class)($shift))->toThrow(ValidationException::class, 'Confirm the assignment');
});

it('refuses approved unavailability over confirmed work', function (): void {
    [, $line, $member] = crewStaffingFixture();
    CrewShift::factory()->confirmed()->create(['crew_member_id' => $member->id, 'starts_at' => $line->starts_at, 'ends_at' => $line->ends_at]);
    $requested = app(RequestCrewAvailability::class)($member, new RequestCrewAvailabilityData('2027-02-10 09:00:00', '2027-02-10 17:00:00', reason: 'Leave'));

    expect(fn () => app(ApproveCrewAvailability::class)(CrewAvailability::findOrFail($requested->id)))->toThrow(ValidationException::class, 'Approved work overlaps');
    \Pest\Laravel\assertDatabaseHas('crew_availabilities', ['id' => $requested->id, 'status' => 'requested']);
});

it('merges availability windows and returns the remaining free intervals', function (): void {
    [, , $member] = crewStaffingFixture();
    CrewAvailability::factory()->create(['crew_member_id' => $member->id, 'status' => 'approved', 'starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 13:00:00']);
    CrewAvailability::factory()->create(['crew_member_id' => $member->id, 'status' => 'approved', 'starts_at' => '2027-02-10 12:00:00', 'ends_at' => '2027-02-10 15:00:00']);

    $windows = app(CrewAvailabilityQuery::class)->windowsFor($member->id, CarbonImmutable::parse('2027-02-10 08:00:00'), CarbonImmutable::parse('2027-02-10 17:00:00'));

    expect($windows->busy)->toHaveCount(1);
    expect($windows->free)->toHaveCount(2);
    expect($windows->free[1])->toBe(['starts_at' => '2027-02-10T15:00:00+00:00', 'ends_at' => '2027-02-10T17:00:00+00:00']);
});

it('excludes unavailable candidates and returns explanations when requested', function (): void {
    [, $line, $member] = crewStaffingFixture();
    CrewAvailability::factory()->create(['crew_member_id' => $member->id, 'status' => 'approved', 'starts_at' => $line->starts_at, 'ends_at' => $line->ends_at]);

    expect(app(CrewCandidateQuery::class)->forLine($line, new MatchCrewCandidatesData))->toHaveCount(0);
    $matches = app(CrewCandidateQuery::class)->forLine($line, new MatchCrewCandidatesData(include_blocked: true));

    expect($matches)->toHaveCount(1);
    expect($matches[0]->blockers[0]['code'])->toBe('UNAVAILABLE');
});

it('denies staffing mutations without the required permission', function (): void {
    [, $line, $member] = crewStaffingFixture();
    \Pest\Laravel\actingAs(User::factory()->create());

    expect(fn () => app(ProposeCrewAssignment::class)($line, new ProposeCrewAssignmentData($member->id)))->toThrow(AuthorizationException::class);
    expect($line->assignments()->count())->toBe(0);
});

it('uses inherited request dates for lines without an explicit period', function (): void {
    [$request, $line, $member] = crewStaffingFixture();
    $line->update(['starts_at' => null, 'ends_at' => null]);

    $proposed = app(ProposeCrewAssignment::class)($line, new ProposeCrewAssignmentData($member->id));
    $assignment = CrewAssignment::findOrFail($proposed->id);
    app(InviteCrewAssignment::class)($assignment);
    app(ConfirmCrewAssignment::class)($assignment->refresh());
    $report = app(CrewConflictQuery::class)->detect($member->id, CarbonImmutable::parse($request->starts_at), CarbonImmutable::parse($request->ends_at));

    expect(array_column($report->hard, 'code'))->toContain('DOUBLE_BOOKED_PROJECT');
});

it('substitutes unworked assignments atomically and keeps the original history', function (): void {
    [, $line, $member] = crewStaffingFixture();
    $original = CrewAssignment::factory()->forLine($line)->forMember($member)->invited()->create();
    app(ConfirmCrewAssignment::class)($original);
    CrewShift::factory()->forAssignment($original)->create(['starts_at' => $line->starts_at, 'ends_at' => $line->ends_at]);
    $replacement = CrewMember::factory()->create(['starts_on' => null, 'ends_on' => null]);
    $replacement->roles()->attach($line->crew_role_id, ['proficiency' => 'competent']);
    CrewRateCard::factory()->forMember($replacement)->create(['effective_from' => '2027-01-01']);

    $result = app(SubstituteCrewAssignment::class)($original->refresh(), new SubstituteCrewAssignmentData($replacement->id, 'Original member unavailable'));

    expect($result->status)->toBe('confirmed');
    expect($result->crew_member_id)->toBe($replacement->id);
    expect($original->refresh()->status)->toBe(CrewAssignmentStatus::Substituted);
    expect($original->active_assignment_key)->toBeNull();
    \Pest\Laravel\assertDatabaseHas('crew_assignments', ['id' => $result->id, 'substitute_for_assignment_id' => $original->id]);
    \Pest\Laravel\assertDatabaseHas('crew_shifts', ['crew_assignment_id' => $result->id, 'crew_member_id' => $replacement->id, 'status' => 'planned']);
    expect($line->refresh()->filled_quantity)->toBe(1);
});

it('rolls substitution back when the replacement lacks an agreed rate', function (): void {
    [, $line, $member] = crewStaffingFixture();
    $original = CrewAssignment::factory()->forLine($line)->forMember($member)->invited()->create();
    app(ConfirmCrewAssignment::class)($original);
    $replacement = CrewMember::factory()->create(['starts_on' => null, 'ends_on' => null]);
    $replacement->roles()->attach($line->crew_role_id, ['proficiency' => 'competent']);

    expect(fn () => app(SubstituteCrewAssignment::class)($original->refresh(), new SubstituteCrewAssignmentData($replacement->id, 'Substitution requested')))->toThrow(ValidationException::class);

    expect($original->refresh()->status)->toBe(CrewAssignmentStatus::Confirmed);
    expect($line->refresh()->filled_quantity)->toBe(1);
    expect($line->assignments()->count())->toBe(1);
});

it('realigns shifts only through an explicit audited operation', function (): void {
    [, $line, $member] = crewStaffingFixture();
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->confirmed()->create(['requires_realignment' => true]);
    $shift = CrewShift::factory()->forAssignment($assignment)->create(['starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 12:00:00']);

    $result = app(RealignAssignmentShifts::class)($assignment, new RealignAssignmentShiftsData([['id' => $shift->id, 'starts_at' => '2027-02-10 10:00:00', 'ends_at' => '2027-02-10 13:00:00', 'timezone' => 'UTC']], 'Call moved by coordinator'));

    expect($result[0]->starts_at)->toBe('2027-02-10T10:00:00+00:00');
    expect($assignment->refresh()->requires_realignment)->toBeFalse();
    \Pest\Laravel\assertDatabaseHas('action_logs', ['action' => 'crew.assignment.realigned', 'auditable_id' => $assignment->id]);
});

it('realigns a confirmed assignment after a next-day project move without changing other shifts or agreed compensation', function (): void {
    [$request, $line, $member] = crewStaffingFixture();
    $request->update(['starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 17:00:00', 'status' => 'filled']);
    $line->update(['quantity' => 2, 'filled_quantity' => 2]);
    $inheritedLine = CrewRequestLine::factory()->create(['crew_request_id' => $request->id]);
    $project = $request->project;
    $project->updateQuietly(['starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 17:00:00']);
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->confirmed()->create(['agreed_at' => now(), 'rate_amount_minor' => 4321, 'exchange_rate_to_base' => '0.950000']);
    $shift = CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 12:00:00']);
    $otherAssignment = CrewAssignment::factory()->forLine($line)->confirmed()->create();
    $otherShift = CrewShift::factory()->forAssignment($otherAssignment)->confirmed()->create(['starts_at' => '2027-02-10 13:00:00', 'ends_at' => '2027-02-10 17:00:00']);
    $agreed = $assignment->only(['rate_amount_minor', 'currency_code', 'exchange_rate_to_base', 'allowances_snapshot']);
    $agreedAt = $assignment->agreed_at->toIso8601String();
    $project->update(['starts_at' => '2027-02-11 09:00:00', 'ends_at' => '2027-02-11 17:00:00']);
    // The observer normally runs after commit; RefreshDatabase keeps this test inside an outer transaction.
    (new CrewProjectScheduleObserver)->updated($project);
    expect($assignment->refresh()->requires_realignment)->toBeTrue();
    expect($shift->refresh()->starts_at->toDateTimeString())->toBe('2027-02-10 09:00:00');

    $result = app(RealignAssignmentShifts::class)($assignment, new RealignAssignmentShiftsData([['id' => $shift->id, 'starts_at' => '2027-02-11 09:00:00', 'ends_at' => '2027-02-11 12:00:00', 'timezone' => 'UTC']], 'Reviewed the next-day project schedule'));

    expect($result[0]->starts_at)->toBe('2027-02-11T09:00:00+00:00');
    expect($assignment->refresh()->requires_realignment)->toBeFalse();
    expect($assignment->only(array_keys($agreed)))->toBe($agreed);
    expect($assignment->agreed_at->toIso8601String())->toBe($agreedAt);
    expect($line->refresh()->ends_at->toDateTimeString())->toBe('2027-02-11 12:00:00');
    expect($request->refresh()->ends_at->toDateTimeString())->toBe('2027-02-11 12:00:00');
    expect($otherShift->refresh()->starts_at->toDateTimeString())->toBe('2027-02-10 13:00:00');
    expect($otherAssignment->refresh()->requires_realignment)->toBeTrue();
    expect($inheritedLine->refresh()->effectiveEndsAt()->toDateTimeString())->toBe('2027-02-10 17:00:00');
    expect($project->refresh()->starts_at->toDateTimeString())->toBe('2027-02-11 09:00:00');
    $audit = ActionLog::where('action', 'crew.shift.updated')->where('auditable_id', $shift->id)->latest('id')->firstOrFail();
    expect($audit->getAttribute('metadata')['old_range'])->toBe(['starts_at' => '2027-02-10T09:00:00+00:00', 'ends_at' => '2027-02-10T12:00:00+00:00', 'timezone' => 'UTC']);
    expect($audit->getAttribute('metadata')['new_range'])->toBe(['starts_at' => '2027-02-11T09:00:00+00:00', 'ends_at' => '2027-02-11T12:00:00+00:00', 'timezone' => 'UTC']);
});

it('rejects realignment expansion while another assignment still inherits the shared line booking window', function (): void {
    [$request, $line, $member] = crewStaffingFixture();
    $request->project->updateQuietly(['starts_at' => '2027-02-11 09:00:00', 'ends_at' => '2027-02-11 17:00:00']);
    $line->update(['quantity' => 2]);
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->confirmed()->create(['requires_realignment' => true]);
    CrewAssignment::factory()->forLine($line)->invited()->create();
    $shift = CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 12:00:00']);

    expect(fn () => app(RealignAssignmentShifts::class)($assignment, new RealignAssignmentShiftsData([['id' => $shift->id, 'starts_at' => '2027-02-11 09:00:00', 'ends_at' => '2027-02-11 12:00:00', 'timezone' => 'UTC']], 'Move one assignment')))->toThrow(ValidationException::class, 'Resolve the other active assignments');

    expect($line->refresh()->ends_at->toDateTimeString())->toBe('2027-02-10 17:00:00');
    expect($shift->refresh()->starts_at->toDateTimeString())->toBe('2027-02-10 09:00:00');
    expect($assignment->refresh()->requires_realignment)->toBeTrue();
});

it('keeps cross-store conflicts blocking explicit realignment after a project date change', function (): void {
    [$request, $line, $member] = crewStaffingFixture();
    $request->project->updateQuietly(['starts_at' => '2027-02-11 09:00:00', 'ends_at' => '2027-02-11 17:00:00']);
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->confirmed()->create(['requires_realignment' => true]);
    $shift = CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 12:00:00']);
    $otherStore = Store::factory()->create();
    CrewShift::factory()->confirmed()->create(['crew_member_id' => $member->id, 'store_id' => $otherStore->id, 'starts_at' => '2027-02-11 10:00:00', 'ends_at' => '2027-02-11 11:00:00']);
    $user = auth()->user();
    $user->update(['is_admin' => false]);
    MemberStore::create(['member_id' => $user->member_id, 'store_id' => $request->store_id, 'created_at' => now()]);
    expect(CrewShift::query()->where('store_id', $otherStore->id)->exists())->toBeFalse();

    expect(fn () => app(RealignAssignmentShifts::class)($assignment, new RealignAssignmentShiftsData([['id' => $shift->id, 'starts_at' => '2027-02-11 09:00:00', 'ends_at' => '2027-02-11 12:00:00', 'timezone' => 'UTC']], 'Move call to reviewed project date')))->toThrow(ValidationException::class, 'another shift during this period');

    expect($shift->refresh()->starts_at->toDateTimeString())->toBe('2027-02-10 09:00:00');
    expect($line->refresh()->ends_at->toDateTimeString())->toBe('2027-02-10 17:00:00');
    expect($assignment->refresh()->requires_realignment)->toBeTrue();
});

it('rejects malformed line requirements without replacing the valid set', function (): void {
    [$request, $line] = crewStaffingFixture();
    $skill = CrewSkill::factory()->create();
    $action = app(SyncCrewRequestLineRequirements::class);
    $action($line, new SyncLineRequirementsData([['requirement_type' => 'skill', 'crew_skill_id' => $skill->id, 'is_mandatory' => true]]));

    expect(fn () => $action($line, new SyncLineRequirementsData([['requirement_type' => 'qualification', 'crew_skill_id' => $skill->id, 'is_mandatory' => true]])))->toThrow(ValidationException::class);

    \Pest\Laravel\assertDatabaseHas('crew_request_line_requirements', ['crew_request_line_id' => $line->id, 'crew_skill_id' => $skill->id]);
});

it('keeps candidate matching query count stable as the candidate pool grows', function (): void {
    [, $line] = crewStaffingFixture();
    $query = app(CrewCandidateQuery::class);
    $query->forLine($line, new MatchCrewCandidatesData);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $query->forLine($line, new MatchCrewCandidatesData);
    $small = count(DB::getQueryLog());
    DB::disableQueryLog();
    foreach (range(1, 5) as $index) {
        $candidate = CrewMember::factory()->create(['starts_on' => null, 'ends_on' => null]);
        $candidate->roles()->attach($line->crew_role_id, ['proficiency' => 'competent']);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();

    $matches = $query->forLine($line, new MatchCrewCandidatesData);

    expect($matches)->toHaveCount(6);
    expect(count(DB::getQueryLog()))->toBe($small);
    DB::disableQueryLog();
});

it('honours the entered timezone and rejects nonexistent spring daylight saving times', function (): void {
    [$from,$to] = StaffingGuard::dates('2027-03-28 00:00:00', '2027-03-28 08:00:00', 'Europe/Athens');
    expect($from->toIso8601String())->toBe('2027-03-27T22:00:00+00:00');
    expect($from->diffInMinutes($to))->toBe(420.0);
    expect(fn () => StaffingGuard::dates('2027-03-28 03:30:00', '2027-03-28 06:00:00', 'Europe/Athens'))->toThrow(ValidationException::class, 'This local time does not exist');
});

it('chooses the first repeated autumn wall time and honours explicit UTC offsets', function (): void {
    [$first] = StaffingGuard::dates('2027-10-31 03:30:00', '2027-10-31 06:00:00', 'Europe/Athens');
    [$second] = StaffingGuard::dates('2027-10-31T03:30:00+02:00', '2027-10-31T06:00:00+02:00', 'Europe/Athens');
    expect($first->toIso8601String())->toBe('2027-10-31T00:30:00+00:00');
    expect($second->toIso8601String())->toBe('2027-10-31T01:30:00+00:00');
});

it('snapshots a dated foreign exchange rate instead of assuming parity', function (): void {
    [, $line, $member, $rate] = crewStaffingFixture();
    settings()->set('company.base_currency', 'GBP');
    $rate->update(['currency_code' => 'EUR']);
    ExchangeRate::factory()->pair('EUR', 'GBP', '0.87654321')->create(['effective_at' => '2027-01-01']);

    $result = app(ProposeCrewAssignment::class)($line, new ProposeCrewAssignmentData($member->id, 'EUR'));

    expect(CrewAssignment::findOrFail($result->id)->exchange_rate_to_base)->toBe('0.876543');
});

it('refuses foreign compensation when no exchange rate can be snapshotted', function (): void {
    [, $line, $member, $rate] = crewStaffingFixture();
    settings()->set('company.base_currency', 'GBP');
    $rate->update(['currency_code' => 'EUR']);

    expect(fn () => app(ProposeCrewAssignment::class)($line, new ProposeCrewAssignmentData($member->id, 'EUR')))->toThrow(ValidationException::class, 'No exchange rate found');
    expect($line->assignments()->count())->toBe(0);
});

it('requires active shifts to finish before cancelling their assignment or request', function (): void {
    [$request, $line, $member] = crewStaffingFixture();
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->confirmed()->create();
    CrewShift::factory()->forAssignment($assignment)->inProgress()->create(['starts_at' => $line->starts_at, 'ends_at' => $line->ends_at]);

    expect(fn () => app(CancelCrewAssignment::class)($assignment, new CancelCrewAssignmentData('Cancel')))->toThrow(ValidationException::class, 'active shift');
    expect(fn () => app(CancelCrewRequest::class)($request, new CancelCrewRequestData('Cancel')))->toThrow(ValidationException::class, 'active shifts');
    expect($assignment->refresh()->status)->toBe(CrewAssignmentStatus::Confirmed);
});

it('refreshes assignment estimates when planned shifts change and clears an unknown estimate after cancellation', function (): void {
    [, $line, $member] = crewStaffingFixture();
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->invited()->create(['min_call_hours' => 0]);
    $created = app(CreateCrewShift::class)($assignment, new CreateCrewShiftData('2027-02-10 09:00:00', '2027-02-10 13:00:00'));
    expect($assignment->refresh()->cost_estimate_minor)->toBe(18000);
    $shift = CrewShift::findOrFail($created->id);

    app(UpdateCrewShift::class)($shift, new UpdateCrewShiftData('2027-02-10 10:00:00', '2027-02-10 13:00:00'));

    expect($assignment->refresh()->cost_estimate_minor)->toBe(13500);
    app(CancelCrewShift::class)($shift->refresh(), new CancelCrewShiftData('Call cancelled'));
    expect($assignment->refresh()->cost_estimate_minor)->toBeNull();
});

it('ranks eligible candidates before applying the requested result limit', function (): void {
    [, $line, $first] = crewStaffingFixture();
    $first->update(['name' => 'A first name']);
    $lead = CrewMember::factory()->create(['name' => 'Z lead engineer', 'starts_on' => null, 'ends_on' => null]);
    $lead->roles()->attach($line->crew_role_id, ['proficiency' => 'lead', 'is_primary' => true]);

    $matches = app(CrewCandidateQuery::class)->forLine($line, new MatchCrewCandidatesData(limit: 1));

    expect($matches)->toHaveCount(1);
    expect($matches[0]->crew_member_id)->toBe($lead->id);
});

it('explains rest constraints in candidate results using the configured severity', function (string $enforcement, bool $eligible, string $severity): void {
    [, $line, $member] = crewStaffingFixture();
    settings()->set('crew.rest_enforcement', $enforcement);
    CrewShift::factory()->confirmed()->create(['crew_member_id' => $member->id, 'starts_at' => '2027-02-10 05:00:00', 'ends_at' => '2027-02-10 08:00:00']);

    $query = app(CrewCandidateQuery::class);
    expect($query->forLine($line, new MatchCrewCandidatesData))->toHaveCount($eligible ? 1 : 0);
    $candidate = $query->forLine($line, new MatchCrewCandidatesData(include_blocked: true))[0];

    if ($severity !== '') {
        expect(array_column($candidate->{$severity}, 'code'))->toContain('REST_BELOW_MINIMUM');
    } else {
        expect($candidate->blockers)->toBe([]);
        expect($candidate->warnings)->toBe([]);
    }
})->with([['hard', false, 'blockers'], ['warning', true, 'warnings'], ['off', true, '']]);

it('explains requested leave and overlapping planned work before proposing a candidate', function (): void {
    [, $line, $member] = crewStaffingFixture();
    CrewShift::factory()->planned()->create(['crew_member_id' => $member->id, 'starts_at' => $line->starts_at, 'ends_at' => $line->ends_at]);
    CrewAvailability::factory()->create(['crew_member_id' => $member->id, 'status' => 'requested', 'starts_at' => $line->starts_at, 'ends_at' => $line->ends_at]);

    $candidate = app(CrewCandidateQuery::class)->forLine($line, new MatchCrewCandidatesData)[0];

    expect($candidate->blockers)->toBe([]);
    expect(array_column($candidate->warnings, 'code'))->toBe(['SHIFT_OVERLAP_PLANNED', 'LEAVE_REQUESTED']);
});

it('reports candidate pool truncation and exposes the next bounded batch', function (): void {
    [, $line] = crewStaffingFixture();
    $members = CrewMember::factory()->count(500)->create();
    $line->role->members()->attach($members->modelKeys(), ['proficiency' => 'competent']);
    $query = app(CrewCandidateQuery::class);

    $first = $query->reportForLine($line, new MatchCrewCandidatesData(limit: 100));
    $next = $query->reportForLine($line, new MatchCrewCandidatesData(limit: 100, offset: 500));

    expect($first->pool_truncated)->toBeTrue();
    expect($first->next_offset)->toBe(500);
    expect($first->candidates)->toHaveCount(100);
    expect($next->pool_truncated)->toBeFalse();
    expect($next->candidates)->toHaveCount(1);
    expect($next->next_offset)->toBeNull();
});
