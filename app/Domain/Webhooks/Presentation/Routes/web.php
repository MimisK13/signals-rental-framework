<?php

declare(strict_types=1);

use App\Domain\Webhooks\Http\Middleware\VerifyInboundWebhookSignature;
use App\Domain\Webhooks\Presentation\Http\Controllers\Web\InboundWebhookController;
use App\Domain\Webhooks\Presentation\Livewire\Index;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/receive/{source}', InboundWebhookController::class)
    ->middleware(['web', VerifyInboundWebhookSignature::class])
    ->name('webhooks.receive');

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('api/webhooks', Index::class)->name('api.webhooks');
});
