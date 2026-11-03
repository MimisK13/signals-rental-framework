<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

arch('Numbering presentation does not depend on Volt', function (): void {
    expect('App\Domain\Numbering\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('keeps shared numbering implementation inside the Numbering domain', function (): void {
    expect(file_exists(app_path('Models/Sequence.php')))->toBeFalse()
        ->and(file_exists(app_path('Models/AutoNumberSequence.php')))->toBeFalse()
        ->and(file_exists(app_path('Services/SequenceAllocator.php')))->toBeFalse()
        ->and(file_exists(app_path('Services/AutoNumberService.php')))->toBeFalse()
        ->and(file_exists(app_path('Support/NumberingAccess.php')))->toBeFalse()
        ->and(file_exists(app_path('Policies/NumberingPolicy.php')))->toBeFalse()
        ->and(file_exists(app_path('Domain/Documents/Models/DocumentSequence.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/admin/settings/numbering.blade.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('admin/settings/numbering")
        ->and(file_get_contents(app_path('Domain/Documents/Presentation/Routes/api.php')))->not->toContain('DocumentSequenceController');
});

it('keeps every Numbering migration scoped to one table', function (): void {
    foreach (File::files(app_path('Domain/Numbering/Database/Migrations')) as $migration) {
        preg_match_all('/Schema::create\(/', $migration->getContents(), $matches);

        expect($matches[0])->toHaveCount(1, "{$migration->getFilename()} must create exactly one table.");
    }
});
