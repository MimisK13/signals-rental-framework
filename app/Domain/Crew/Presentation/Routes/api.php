<?php

declare(strict_types=1);

use App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew\CrewAssignmentController;
use App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew\CrewAvailabilityController;
use App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew\CrewMemberController;
use App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew\CrewReportController;
use App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew\CrewRequestController;
use App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew\CrewShiftController;
use App\Domain\Crew\Presentation\Http\Controllers\Api\V1\Crew\CrewTimesheetController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/crew')->name('api.v1.crew.')->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user', 'module:crew', 'can:crew.access'])->group(function (): void {
    Route::get('members', [CrewMemberController::class, 'index'])->name('members.index');
    Route::get('members/{crewMember}', [CrewMemberController::class, 'show'])->name('members.show');
    Route::post('members', [CrewMemberController::class, 'store'])->middleware('idempotency')->name('members.store');
    Route::patch('members/{crewMember}', [CrewMemberController::class, 'update'])->middleware('idempotency')->name('members.update');
    Route::delete('members/{crewMember}', [CrewMemberController::class, 'destroy'])->middleware('idempotency')->name('members.destroy');
    Route::get('requests', [CrewRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/{crewRequest}', [CrewRequestController::class, 'show'])->name('requests.show');
    Route::post('requests', [CrewRequestController::class, 'store'])->middleware('idempotency')->name('requests.store');
    Route::patch('requests/{crewRequest}', [CrewRequestController::class, 'update'])->middleware('idempotency')->name('requests.update');
    Route::post('requests/{crewRequest}/publish', [CrewRequestController::class, 'publish'])->middleware('idempotency')->name('requests.publish');
    Route::post('requests/{crewRequest}/cancel', [CrewRequestController::class, 'cancel'])->middleware('idempotency')->name('requests.cancel');
    Route::get('assignments', [CrewAssignmentController::class, 'index'])->name('assignments.index');
    Route::get('assignments/{crewAssignment}', [CrewAssignmentController::class, 'show'])->name('assignments.show');
    Route::post('request-lines/{crewRequestLine}/assignments', [CrewAssignmentController::class, 'store'])->middleware('idempotency')->name('assignments.store');
    Route::post('assignments/{crewAssignment}/invite', [CrewAssignmentController::class, 'invite'])->middleware('idempotency')->name('assignments.invite');
    Route::post('assignments/{crewAssignment}/confirm', [CrewAssignmentController::class, 'confirm'])->middleware('idempotency')->name('assignments.confirm');
    Route::post('assignments/{crewAssignment}/reject', [CrewAssignmentController::class, 'reject'])->middleware('idempotency')->name('assignments.reject');
    Route::post('assignments/{crewAssignment}/cancel', [CrewAssignmentController::class, 'cancel'])->middleware('idempotency')->name('assignments.cancel');
    Route::post('assignments/{crewAssignment}/substitute', [CrewAssignmentController::class, 'substitute'])->middleware('idempotency')->name('assignments.substitute');
    Route::get('shifts', [CrewShiftController::class, 'index'])->name('shifts.index');
    Route::get('shifts/{crewShift}', [CrewShiftController::class, 'show'])->name('shifts.show');
    Route::post('assignments/{crewAssignment}/shifts', [CrewShiftController::class, 'store'])->middleware('idempotency')->name('shifts.store');
    Route::patch('shifts/{crewShift}', [CrewShiftController::class, 'update'])->name('shifts.update');
    Route::post('shifts/{crewShift}/confirm', [CrewShiftController::class, 'confirm'])->middleware('idempotency')->name('shifts.confirm');
    Route::delete('shifts/{crewShift}', [CrewShiftController::class, 'destroy'])->middleware('idempotency')->name('shifts.destroy');
    Route::get('availabilities', [CrewAvailabilityController::class, 'index'])->name('availabilities.index');
    Route::get('availabilities/{crewAvailability}', [CrewAvailabilityController::class, 'show'])->name('availabilities.show');
    Route::post('members/{crewMember}/availabilities', [CrewAvailabilityController::class, 'store'])->middleware('idempotency')->name('availabilities.store');
    Route::post('availabilities/{crewAvailability}/approve', [CrewAvailabilityController::class, 'approve'])->middleware('idempotency')->name('availabilities.approve');
    Route::post('availabilities/{crewAvailability}/reject', [CrewAvailabilityController::class, 'reject'])->middleware('idempotency')->name('availabilities.reject');
    Route::post('availabilities/{crewAvailability}/cancel', [CrewAvailabilityController::class, 'cancel'])->middleware('idempotency')->name('availabilities.cancel');
    Route::get('timesheets', [CrewTimesheetController::class, 'index'])->name('timesheets.index');
    Route::get('timesheets/{crewTimesheet}', [CrewTimesheetController::class, 'show'])->name('timesheets.show');
    Route::post('timesheets/{crewTimesheet}/submit', [CrewTimesheetController::class, 'submit'])->middleware('idempotency')->name('timesheets.submit');
    Route::post('timesheets/{crewTimesheet}/approve', [CrewTimesheetController::class, 'approve'])->middleware('idempotency')->name('timesheets.approve');
    Route::post('timesheets/{crewTimesheet}/reject', [CrewTimesheetController::class, 'reject'])->middleware('idempotency')->name('timesheets.reject');
    Route::post('timesheets/{crewTimesheet}/lock', [CrewTimesheetController::class, 'lock'])->middleware('idempotency')->name('timesheets.lock');

    Route::post('requests/{crewRequest}/lines', [CrewRequestController::class, 'addLine'])->middleware('idempotency')->name('requests.lines.store');
    Route::post('assignments/{crewAssignment}/timesheets', [CrewTimesheetController::class, 'store'])->middleware('idempotency')->name('timesheets.store');
    Route::post('timesheets/{crewTimesheet}/entries', [CrewTimesheetController::class, 'addEntry'])->middleware('idempotency')->name('timesheets.entries.store');
    Route::post('timesheets/{crewTimesheet}/export', [CrewTimesheetController::class, 'export'])->middleware('idempotency')->name('timesheets.export');
    Route::get('reports/coverage', [CrewReportController::class, 'coverage'])->name('reports.coverage');
    Route::get('reports/cost-forecast', [CrewReportController::class, 'costForecast'])->name('reports.cost-forecast');
    Route::get('reports/utilisation', [CrewReportController::class, 'utilisation'])->name('reports.utilisation');
    Route::get('members/{crewMember}/work-history', [CrewReportController::class, 'workHistory'])->name('members.work-history');
    Route::get('roles', [CrewReportController::class, 'roles'])->name('roles.index');
    Route::get('request-lines/{crewRequestLine}/candidates', [CrewReportController::class, 'candidates'])->name('candidates.index');
});
