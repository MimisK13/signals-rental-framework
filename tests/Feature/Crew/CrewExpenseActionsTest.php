<?php

declare(strict_types=1);

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Attachments\Models\Attachment;
use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Crew\Actions\Expenses\ApproveCrewExpense;
use App\Domain\Crew\Actions\Expenses\CreateCrewExpense;
use App\Domain\Crew\Actions\Expenses\MarkCrewExpenseReimbursed;
use App\Domain\Crew\Actions\Expenses\RejectCrewExpense;
use App\Domain\Crew\Actions\Expenses\SubmitCrewExpense;
use App\Domain\Crew\Actions\Expenses\UpdateCrewExpense;
use App\Domain\Crew\Data\Expenses\CreateCrewExpenseData;
use App\Domain\Crew\Data\Expenses\MarkReimbursedData;
use App\Domain\Crew\Data\Expenses\RejectCrewExpenseData;
use App\Domain\Crew\Data\Expenses\UpdateCrewExpenseData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Models\CrewMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function crewExpenseActor(): void
{
    \Pest\Laravel\seed(PermissionSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo(['crew.expenses.create', 'crew.expenses.view', 'crew.expenses.approve']);
    \Pest\Laravel\actingAs($user);
}

it('converts a submitted claim once and records reimbursement as a finance reference', function (): void {
    $this->travelTo('2026-11-02 12:00:00');
    crewExpenseActor();
    $assignment = CrewAssignment::factory()->confirmed()->create();
    $data = new CreateCrewExpenseData($assignment->store_id, $assignment->project_id, 'travel', '2026-11-02', 'Travel receipt', 1999, 'EUR', '0.875000', $assignment->id);
    $created = app(CreateCrewExpense::class)($assignment->crewMember, $data);
    $expense = CrewExpense::query()->findOrFail($created->id);
    $receipt = Attachment::factory()->for($expense, 'attachable')->create(['category' => 'receipt']);
    app(SubmitCrewExpense::class)($expense);
    app(ApproveCrewExpense::class)($expense->fresh());

    $result = app(MarkCrewExpenseReimbursed::class)($expense->fresh(), new MarkReimbursedData('PAYABLE-42'));

    expect($result->amount_base_minor)->toBe(1749);
    expect($result->status)->toBe('reimbursed');
    expect($expense->attachments()->whereKey($receipt->id)->exists())->toBeTrue();
    $this->assertDatabaseHas('crew_expenses', ['id' => $expense->id, 'finance_reference' => 'PAYABLE-42', 'amount_base_minor' => 1749]);
    expect(ActionLog::query()->where('action', 'crew.expense.reimbursed')->exists())->toBeTrue();
});

it('returns a rejected claim to draft on correction before resubmission', function (): void {
    crewExpenseActor();
    $expense = CrewExpense::factory()->submitted()->create();
    app(RejectCrewExpense::class)($expense, new RejectCrewExpenseData('Wrong date.'));
    $data = new UpdateCrewExpenseData($expense->store_id, $expense->project_id, 'travel', '2026-11-02', 'Corrected claim', 1000, 'GBP', '1.000000');
    app(UpdateCrewExpense::class)($expense->fresh(), $data);

    $result = app(SubmitCrewExpense::class)($expense->fresh());

    expect($result->status)->toBe('submitted');
    expect($result->amount_base_minor)->toBe(1000);
});

it('rejects assignment links belonging to another member', function (): void {
    crewExpenseActor();
    $assignment = CrewAssignment::factory()->confirmed()->create();
    $other = CrewMember::factory()->create();
    $data = new CreateCrewExpenseData($assignment->store_id, $assignment->project_id, 'travel', '2026-11-02', 'Claim', 1000, 'GBP', '1.000000', $assignment->id);

    expect(function () use ($other, $data): void {
        app(CreateCrewExpense::class)($other, $data);
    })->toThrow(ValidationException::class);

    $this->assertDatabaseCount('crew_expenses', 0);
});

it('rejects editing a claim after approval', function (): void {
    crewExpenseActor();
    $expense = CrewExpense::factory()->approved()->create();
    $data = new UpdateCrewExpenseData($expense->store_id, $expense->project_id, 'travel', '2026-11-02', 'Changed claim', 1000, 'GBP', '1.000000');

    expect(function () use ($expense, $data): void {
        app(UpdateCrewExpense::class)($expense, $data);
    })->toThrow(AuthorizationException::class);
    expect(function () use ($expense): void {
        $expense->update(['amount_minor' => 1]);
    })->toThrow(ValidationException::class);
});

it('rejects a negative expense before creating any financial record', function (): void {
    crewExpenseActor();
    $assignment = CrewAssignment::factory()->confirmed()->create();
    $data = new CreateCrewExpenseData($assignment->store_id, $assignment->project_id, 'travel', '2026-11-02', 'Claim', -1, 'GBP', '1.000000');

    expect(function () use ($assignment, $data): void {
        app(CreateCrewExpense::class)($assignment->crewMember, $data);
    })->toThrow(ValidationException::class);

    $this->assertDatabaseCount('crew_expenses', 0);
});

it('rejects an unsupported expense currency before creating an unrenderable claim', function (): void {
    crewExpenseActor();
    $assignment = CrewAssignment::factory()->confirmed()->create();
    $data = new CreateCrewExpenseData($assignment->store_id, $assignment->project_id, 'travel', '2026-11-02', 'Claim', 1000, 'ZZZ', '1.000000');

    expect(function () use ($assignment, $data): void {
        app(CreateCrewExpense::class)($assignment->crewMember, $data);
    })->toThrow(ValidationException::class);

    $this->assertDatabaseCount('crew_expenses', 0);
});
