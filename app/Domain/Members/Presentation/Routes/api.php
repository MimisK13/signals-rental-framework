<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Members\Presentation\Http\Controllers\Api\V1\MemberAddressController;
use App\Domain\Members\Presentation\Http\Controllers\Api\V1\MemberController;
use App\Domain\Members\Presentation\Http\Controllers\Api\V1\MemberEmailController;
use App\Domain\Members\Presentation\Http\Controllers\Api\V1\MemberLinkController;
use App\Domain\Members\Presentation\Http\Controllers\Api\V1\MemberNotificationPreferenceController;
use App\Domain\Members\Presentation\Http\Controllers\Api\V1\MemberPhoneController;
use App\Domain\Members\Presentation\Http\Controllers\Api\V1\MemberRelationshipController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::get('members/{member}/notification_preferences', [MemberNotificationPreferenceController::class, 'index'])->name('api.v1.members.notification_preferences.index');
        Route::put('members/{member}/notification_preferences', [MemberNotificationPreferenceController::class, 'update'])->name('api.v1.members.notification_preferences.update');
        Route::apiResource('members', MemberController::class)->names('api.v1.members');
        Route::post('members/{member}/merge', [MemberController::class, 'merge'])->name('api.v1.members.merge');
        Route::post('members/{member}/anonymise', [MemberController::class, 'anonymise'])->name('api.v1.members.anonymise');
        Route::post('members/{member}/statement/email', [MemberController::class, 'emailStatement'])->name('api.v1.members.statement.email');
        Route::post('members/{member}/restore', [MemberController::class, 'restore'])
            ->withTrashed()
            ->name('api.v1.members.restore');
        Route::apiResource('members.addresses', MemberAddressController::class)->except(['show'])->names('api.v1.members.addresses');
        Route::apiResource('members.emails', MemberEmailController::class)->except(['show'])->names('api.v1.members.emails');
        Route::apiResource('members.phones', MemberPhoneController::class)->except(['show'])->names('api.v1.members.phones');
        Route::apiResource('members.links', MemberLinkController::class)->except(['show'])->names('api.v1.members.links');
        Route::apiResource('members.relationships', MemberRelationshipController::class)->only(['index', 'store', 'destroy'])->names('api.v1.members.relationships');
    });
