<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

arch('Administration presentation does not depend on Volt', function (): void {
    expect('App\Domain\Administration\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('keeps user and permission administration inside the Administration domain', function (): void {
    expect(file_exists(app_path('Actions/Admin/CreateRole.php')))->toBeFalse()
        ->and(file_exists(app_path('Actions/Admin/InviteUser.php')))->toBeFalse()
        ->and(file_exists(app_path('Actions/Admin/UpdateUser.php')))->toBeFalse()
        ->and(file_exists(app_path('Models/User.php')))->toBeFalse()
        ->and(file_exists(app_path('Services/PermissionRegistry.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/admin/settings/users.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/admin/index.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/settings/profile.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/settings/delete-user-form.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/settings/appearance.blade.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('admin/settings/users")
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('admin'")
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('settings/profile")
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('settings/appearance")
        ->and(file_exists(base_path('routes/api.php')))->toBeFalse();
});

it('keeps every Administration migration scoped to at most one table', function (): void {
    foreach (File::files(app_path('Domain/Administration/Database/Migrations')) as $migration) {
        preg_match_all('/Schema::create\(/', $migration->getContents(), $matches);
        $expectedCount = str_contains($migration->getFilename(), '_create_') ? 1 : 0;

        expect($matches[0])->toHaveCount($expectedCount, "{$migration->getFilename()} has an invalid table scope.");
    }
});
