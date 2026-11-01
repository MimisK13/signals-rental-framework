<?php

declare(strict_types=1);

use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewAvailabilityStatus;
use App\Domain\Crew\Enums\CrewExpenseStatus;
use App\Domain\Crew\Enums\CrewMemberStatus;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Enums\CrewTimesheetStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Services\CrewStatusRegistry;

/**
 * @return array<string, array{0: object, 1: object, 2: bool}>
 */
function crewTransitionCases(): array
{
    return [
        'request draft to open' => [CrewRequestStatus::Draft, CrewRequestStatus::Open, true],
        'request filled to draft' => [CrewRequestStatus::Filled, CrewRequestStatus::Draft, false],
        'request cancelled is terminal' => [CrewRequestStatus::Cancelled, CrewRequestStatus::Open, false],
        'assignment proposed to invited' => [CrewAssignmentStatus::Proposed, CrewAssignmentStatus::Invited, true],
        'assignment invited to confirmed' => [CrewAssignmentStatus::Invited, CrewAssignmentStatus::Confirmed, true],
        'assignment proposed to confirmed' => [CrewAssignmentStatus::Proposed, CrewAssignmentStatus::Confirmed, false],
        'assignment confirmed to substituted' => [CrewAssignmentStatus::Confirmed, CrewAssignmentStatus::Substituted, true],
        'shift planned to confirmed' => [CrewShiftStatus::Planned, CrewShiftStatus::Confirmed, true],
        'shift confirmed to in progress' => [CrewShiftStatus::Confirmed, CrewShiftStatus::InProgress, true],
        'shift completed is terminal' => [CrewShiftStatus::Completed, CrewShiftStatus::Cancelled, false],
        'timesheet draft to approved' => [CrewTimesheetStatus::Draft, CrewTimesheetStatus::Approved, false],
        'timesheet approved to locked' => [CrewTimesheetStatus::Approved, CrewTimesheetStatus::Locked, true],
        'timesheet locked is terminal' => [CrewTimesheetStatus::Locked, CrewTimesheetStatus::Draft, false],
        'expense submitted to approved' => [CrewExpenseStatus::Submitted, CrewExpenseStatus::Approved, true],
        'expense approved to reimbursed' => [CrewExpenseStatus::Approved, CrewExpenseStatus::Reimbursed, true],
        'expense reimbursed is terminal' => [CrewExpenseStatus::Reimbursed, CrewExpenseStatus::Approved, false],
        'member archived to active' => [CrewMemberStatus::Archived, CrewMemberStatus::Active, true],
        'availability rejected is terminal' => [CrewAvailabilityStatus::Rejected, CrewAvailabilityStatus::Approved, false],
    ];
}

it('enforces the documented enum transition graph', function (CrewAssignmentStatus|CrewAvailabilityStatus|CrewExpenseStatus|CrewMemberStatus|CrewRequestStatus|CrewShiftStatus|CrewTimesheetStatus $from, CrewAssignmentStatus|CrewAvailabilityStatus|CrewExpenseStatus|CrewMemberStatus|CrewRequestStatus|CrewShiftStatus|CrewTimesheetStatus $to, bool $allowed): void {
    expect(call_user_func([$from, 'canTransitionTo'], $to))->toBe($allowed);
    expect($from->label())->toBeString();
    expect($from->label())->not->toBeEmpty();
})->with(crewTransitionCases());

it('mirrors the enum graph in the status registry', function (): void {
    $registry = app(CrewStatusRegistry::class);

    expect($registry->statuses(CrewRequest::class))->toBe([
        'draft', 'open', 'partially_filled', 'filled', 'cancelled',
    ])
        ->and($registry->canTransitionTo(CrewRequest::class, 'draft', 'open'))->toBeTrue()
        ->and($registry->canTransitionTo(CrewRequest::class, 'cancelled', 'open'))->toBeFalse()
        ->and($registry->canTransitionTo(CrewShift::class, 'planned', 'confirmed'))->toBeTrue()
        ->and($registry->canTransitionTo(CrewTimesheet::class, 'draft', 'approved'))->toBeFalse();
});

it('treats a transition to the same status as a no-op', function (): void {
    app(CrewStatusRegistry::class)->assertCanTransitionTo(CrewExpense::class, 'draft', 'draft');

    expect(true)->toBeTrue();
});

it('rejects unknown statuses', function (string $model, string $status): void {
    app(CrewStatusRegistry::class)->assertKnown($model, $status);
})->with([
    'request' => [CrewRequest::class, 'bogus'],
    'assignment' => [CrewAssignment::class, 'pending'],
    'shift' => [CrewShift::class, 'draft'],
])->throws(InvalidArgumentException::class);

it('rejects an invalid transition with a readable message', function (): void {
    app(CrewStatusRegistry::class)->assertCanTransitionTo(CrewAssignment::class, 'rejected', 'confirmed');
})->throws(InvalidArgumentException::class, 'CrewAssignment status cannot transition from [rejected] to [confirmed].');

it('rejects a model without a status lifecycle', function (): void {
    app(CrewStatusRegistry::class)->statuses(stdClass::class);
})->throws(InvalidArgumentException::class);
