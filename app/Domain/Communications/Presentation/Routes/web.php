<?php

declare(strict_types=1);

use App\Domain\Communications\Presentation\Http\Controllers\CommunicationUnsubscribeController;
use App\Domain\Communications\Presentation\Livewire\CommunicationLog;
use App\Domain\Communications\Presentation\Livewire\CommunicationTemplateEditor;
use App\Domain\Communications\Presentation\Livewire\EmailTemplateForm;
use App\Domain\Communications\Presentation\Livewire\EmailTemplates;
use App\Domain\Communications\Presentation\Livewire\MemberCommunications;
use App\Domain\Communications\Presentation\Livewire\OpportunityCommunications;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'signed', 'throttle:'.config('communications.unsubscribe_throttle', '10,1')])->group(function (): void {
    Route::get('communications/unsubscribe/{member}', [CommunicationUnsubscribeController::class, 'show'])
        ->name('communications.unsubscribe.show');
    Route::post('communications/unsubscribe/{member}', [CommunicationUnsubscribeController::class, 'confirm'])
        ->name('communications.unsubscribe.confirm');
});

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('members/{member}/communications', MemberCommunications::class)
        ->name('members.communications');
    Route::livewire('opportunities/{opportunity}/communications', OpportunityCommunications::class)
        ->name('opportunities.communications')
        ->withTrashed();
});

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('admin/settings/email-templates', EmailTemplates::class)
        ->name('admin.settings.email-templates');
    Route::livewire('admin/settings/email-templates/{template}/edit', EmailTemplateForm::class)
        ->name('admin.settings.email-templates.edit');
    Route::livewire('admin/settings/communication-log', CommunicationLog::class)
        ->name('admin.settings.communication-log');
    Route::livewire('admin/settings/communication-templates/{communicationTemplate}/edit', CommunicationTemplateEditor::class)
        ->name('admin.settings.communication-templates.edit');
});
