<?php

declare(strict_types=1);
use App\Domain\Setup\Actions\CheckInfrastructure;

arch('System presentation does not depend on Volt', function (): void {
    expect('App\Domain\System\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('keeps operational system implementation inside the System domain', function (): void {
    expect(file_exists(app_path('Services/SystemHealthService.php')))->toBeFalse()
        ->and(file_exists(app_path('Services/ConnectionTesters/PostgresConnectionTester.php')))->toBeFalse()
        ->and(file_exists(app_path('Console/Commands/SignalsStatusCommand.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/admin/settings/system-health.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/admin/settings/infrastructure.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/admin/settings/seeders.blade.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('admin/settings/system-health")
        ->and(class_exists(CheckInfrastructure::class))->toBeTrue();
});
