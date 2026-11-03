<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Warehouse\Containers\Presentation\Http\Controllers\Api\V1\ContainerController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        Route::post('containers/temporary', [ContainerController::class, 'storeTemporary'])->name('api.v1.containers.temporary.store');
        Route::post('containers/repack/start', [ContainerController::class, 'startRepack'])->name('api.v1.containers.repack.start');
        Route::post('containers/{container}/pack', [ContainerController::class, 'pack'])->name('api.v1.containers.pack');
        Route::post('containers/{container}/transfer', [ContainerController::class, 'transfer'])->name('api.v1.containers.transfer');
        Route::post('containers/{container}/unpack', [ContainerController::class, 'unpack'])->name('api.v1.containers.unpack');
        Route::post('containers/{container}/seal', [ContainerController::class, 'seal'])->name('api.v1.containers.seal');
        Route::post('containers/{container}/unseal', [ContainerController::class, 'unseal'])->name('api.v1.containers.unseal');
        Route::post('containers/{container}/dissolve', [ContainerController::class, 'dissolve'])->name('api.v1.containers.dissolve');
        Route::post('containers/{container}/dispatch', [ContainerController::class, 'dispatch'])->name('api.v1.containers.dispatch');
        Route::post('containers/{container}/return', [ContainerController::class, 'returnKit'])->name('api.v1.containers.return');
        Route::post('containers/{container}/repack', [ContainerController::class, 'repackItem'])->name('api.v1.containers.repack.item');
        Route::post('containers/{container}/verify-checkin', [ContainerController::class, 'verifyCheckin'])->name('api.v1.containers.verify-checkin');
        Route::get('containers/{container}/compliance', [ContainerController::class, 'compliance'])->name('api.v1.containers.compliance');
        Route::get('containers', [ContainerController::class, 'index'])->name('api.v1.containers.index');
        Route::post('containers', [ContainerController::class, 'store'])->name('api.v1.containers.store');
        Route::get('containers/{container}', [ContainerController::class, 'show'])->name('api.v1.containers.show');
        Route::patch('containers/{container}', [ContainerController::class, 'update'])->name('api.v1.containers.update');
    });
