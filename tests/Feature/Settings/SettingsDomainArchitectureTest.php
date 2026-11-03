<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

arch('Settings presentation does not depend on Volt', function (): void {
    expect('App\Domain\Settings\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('keeps shared settings implementation inside the Settings domain', function (): void {
    expect(file_exists(app_path('Models/Setting.php')))->toBeFalse()
        ->and(file_exists(app_path('Services/SettingsService.php')))->toBeFalse()
        ->and(file_exists(app_path('Services/SettingsRegistry.php')))->toBeFalse()
        ->and(file_exists(app_path('Settings/SettingsDefinition.php')))->toBeFalse()
        ->and(file_exists(app_path('Providers/SettingsServiceProvider.php')))->toBeFalse()
        ->and(file_exists(app_path('Http/Controllers/Api/V1/SettingsController.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/admin/settings/company.blade.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('admin/settings/company")
        ->and(file_exists(base_path('routes/api.php')))->toBeFalse();
});

it('keeps every Settings migration scoped to at most one table', function (): void {
    foreach (File::files(app_path('Domain/Settings/Database/Migrations')) as $migration) {
        preg_match_all('/Schema::(?:create|table)\(/', $migration->getContents(), $matches);

        expect(count($matches[0]))->toBeLessThanOrEqual(1, "{$migration->getFilename()} targets multiple tables.");
    }
});
