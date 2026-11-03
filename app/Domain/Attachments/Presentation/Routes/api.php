<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Attachments\Presentation\Http\Controllers\Api\V1\AttachmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::get('members/{member}/attachments', [AttachmentController::class, 'indexForMember'])
            ->name('api.v1.members.attachments.index');
        Route::apiResource('attachments', AttachmentController::class)
            ->only(['show', 'store', 'destroy'])
            ->names('api.v1.attachments');
    });
