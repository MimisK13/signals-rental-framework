<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

arch('Auth presentation does not depend on Volt', function (): void {
    expect('App\Domain\Auth\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('keeps authentication implementation inside the Auth domain', function (): void {
    expect(file_exists(app_path('Actions/Auth')))->toBeFalse()
        ->and(file_exists(app_path('Services/Auth')))->toBeFalse()
        ->and(file_exists(app_path('Http/Controllers/Auth')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/auth')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/settings/password.blade.php')))->toBeFalse()
        ->and(file_exists(base_path('routes/auth.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('invitation/")
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('settings/password")
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("require __DIR__.'/auth.php'");
});

it('keeps every Auth migration scoped to one table', function (): void {
    foreach (File::files(app_path('Domain/Auth/Database/Migrations')) as $migration) {
        preg_match_all('/Schema::create\(/', $migration->getContents(), $matches);

        expect($matches[0])->toHaveCount(1, "{$migration->getFilename()} must create exactly one table.");
    }
});
