<?php

declare(strict_types=1);

arch('notifications presentation does not depend on Volt', function (): void {
    expect('App\Domain\Notifications\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('has no legacy Notifications Volt views or routes', function (): void {
    expect(is_dir(resource_path('views/livewire/notifications')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/settings/notifications.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/admin/settings/notifications.blade.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('settings/notifications")
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('admin/settings/notifications");
});
