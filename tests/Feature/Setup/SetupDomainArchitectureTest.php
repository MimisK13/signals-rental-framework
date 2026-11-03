<?php

declare(strict_types=1);

arch('setup presentation does not depend on Volt', function (): void {
    expect('App\Domain\Setup\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('has no legacy Setup Volt view, layout, or route', function (): void {
    expect(is_dir(resource_path('views/livewire/setup')))->toBeFalse()
        ->and(file_exists(resource_path('views/components/layouts/setup.blade.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('setup");
});
