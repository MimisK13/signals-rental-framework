<?php

declare(strict_types=1);

use App\Domain\Attachments\Presentation\Livewire\MemberFiles;
use App\Domain\Attachments\Presentation\Livewire\OpportunityFiles;
use App\Domain\Attachments\Presentation\Livewire\ProductFiles;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('members/{member}/files', MemberFiles::class)->name('members.files');
    Route::livewire('products/{product}/files', ProductFiles::class)->name('products.files');
    Route::livewire('opportunities/{opportunity}/files', OpportunityFiles::class)
        ->name('opportunities.files')
        ->withTrashed();
});
