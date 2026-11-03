<?php

declare(strict_types=1);

arch('Activities domain presentation does not depend on Volt', function (): void {
    expect('App\Domain\Activities')->not->toUse('Livewire\Volt');
});

it('owns calendar settings presentation without a root Volt route', function (): void {
    expect(file_exists(resource_path('views/livewire/settings/calendar.blade.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('settings/calendar");
});
