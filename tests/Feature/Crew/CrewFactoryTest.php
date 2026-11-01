<?php

declare(strict_types=1);

use App\Domain\Crew\Database\Factories\CrewAssignmentFactory;
use App\Domain\Crew\Database\Factories\CrewAvailabilityFactory;
use App\Domain\Crew\Database\Factories\CrewExpenseFactory;
use App\Domain\Crew\Database\Factories\CrewMemberFactory;
use App\Domain\Crew\Database\Factories\CrewMemberQualificationFactory;
use App\Domain\Crew\Database\Factories\CrewMemberRoleFactory;
use App\Domain\Crew\Database\Factories\CrewMemberSkillFactory;
use App\Domain\Crew\Database\Factories\CrewQualificationFactory;
use App\Domain\Crew\Database\Factories\CrewRateCardFactory;
use App\Domain\Crew\Database\Factories\CrewRequestFactory;
use App\Domain\Crew\Database\Factories\CrewRequestLineFactory;
use App\Domain\Crew\Database\Factories\CrewRequestLineRequirementFactory;
use App\Domain\Crew\Database\Factories\CrewRoleFactory;
use App\Domain\Crew\Database\Factories\CrewRoleRequirementFactory;
use App\Domain\Crew\Database\Factories\CrewShiftBreakFactory;
use App\Domain\Crew\Database\Factories\CrewShiftFactory;
use App\Domain\Crew\Database\Factories\CrewSkillFactory;
use App\Domain\Crew\Database\Factories\CrewTimeEntryFactory;
use App\Domain\Crew\Database\Factories\CrewTimesheetFactory;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewAvailabilityStatus;
use App\Domain\Crew\Enums\CrewExpenseStatus;
use App\Domain\Crew\Enums\CrewMemberStatus;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Enums\CrewTimesheetStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewMemberQualification;
use App\Domain\Crew\Models\CrewMemberRole;
use App\Domain\Crew\Models\CrewMemberSkill;
use App\Domain\Crew\Models\CrewQualification;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewRequestLineRequirement;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Models\CrewRoleRequirement;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewShiftBreak;
use App\Domain\Crew\Models\CrewSkill;
use App\Domain\Crew\Models\CrewTimeEntry;
use App\Domain\Crew\Models\CrewTimesheet;
use Illuminate\Support\Str;

it('resolves and creates every crew factory', function (string $factoryClass, string $modelClass): void {
    $model = $factoryClass::new()->create();

    expect($model)->toBeInstanceOf($modelClass)
        ->and($model->exists)->toBeTrue();
})->with([
    'role' => [CrewRoleFactory::class, CrewRole::class],
    'skill' => [CrewSkillFactory::class, CrewSkill::class],
    'qualification' => [CrewQualificationFactory::class, CrewQualification::class],
    'member' => [CrewMemberFactory::class, CrewMember::class],
    'member role' => [CrewMemberRoleFactory::class, CrewMemberRole::class],
    'member skill' => [CrewMemberSkillFactory::class, CrewMemberSkill::class],
    'member qualification' => [CrewMemberQualificationFactory::class, CrewMemberQualification::class],
    'role requirement' => [CrewRoleRequirementFactory::class, CrewRoleRequirement::class],
    'rate card' => [CrewRateCardFactory::class, CrewRateCard::class],
    'request' => [CrewRequestFactory::class, CrewRequest::class],
    'request line' => [CrewRequestLineFactory::class, CrewRequestLine::class],
    'request line requirement' => [CrewRequestLineRequirementFactory::class, CrewRequestLineRequirement::class],
    'assignment' => [CrewAssignmentFactory::class, CrewAssignment::class],
    'shift' => [CrewShiftFactory::class, CrewShift::class],
    'availability' => [CrewAvailabilityFactory::class, CrewAvailability::class],
    'timesheet' => [CrewTimesheetFactory::class, CrewTimesheet::class],
    'time entry' => [CrewTimeEntryFactory::class, CrewTimeEntry::class],
    'expense' => [CrewExpenseFactory::class, CrewExpense::class],
    'shift break' => [CrewShiftBreakFactory::class, CrewShiftBreak::class],
]);

it('exposes factory states for lifecycle transitions', function (): void {
    $request = CrewRequest::factory()->open()->create();
    $assignment = CrewAssignment::factory()->confirmed()->create();
    $shift = CrewShift::factory()->overnight()->create();
    $timesheet = CrewTimesheet::factory()->approved()->withEntry()->create();
    $expense = CrewExpense::factory()->reimbursed()->create();
    $availability = CrewAvailability::factory()->approved()->allDay()->create();
    $member = CrewMember::factory()->freelancer()->withoutUser()->create();

    expect($request->status)->toBe(CrewRequestStatus::Open)
        ->and($assignment->status)->toBe(CrewAssignmentStatus::Confirmed)
        ->and($shift->status)->toBe(CrewShiftStatus::Planned)
        ->and($shift->is_overnight)->toBeTrue()
        ->and($timesheet->status)->toBe(CrewTimesheetStatus::Approved)
        ->and($timesheet->entries)->toHaveCount(1)
        ->and($expense->status)->toBe(CrewExpenseStatus::Reimbursed)
        ->and($availability->status)->toBe(CrewAvailabilityStatus::Approved)
        ->and($member->status)->toBe(CrewMemberStatus::Active)
        ->and($member->user_id)->toBeNull();
});

it('keeps crew models and factories inside the domain namespace', function (string $class): void {
    expect(class_exists($class))->toBeTrue()
        ->and(Str::startsWith($class, 'App\\Domain\\Crew\\'))->toBeTrue();
})->with([
    CrewMember::class,
    CrewRequest::class,
    CrewAssignment::class,
    CrewShift::class,
    CrewTimesheet::class,
    CrewExpense::class,
    CrewMemberFactory::class,
    CrewShiftFactory::class,
]);

it('generates sequential day and overnight shift boundaries', function (): void {
    $shift = CrewShift::factory()->acrossSpringForward()->create();

    expect($shift->ends_at->greaterThan($shift->starts_at))->toBeTrue()
        ->and(abs($shift->ends_at->diffInMinutes($shift->starts_at)))->toBe(480.0);
});
