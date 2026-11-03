<?php

declare(strict_types=1);

arch('stores presentation does not depend on Volt', function (): void {
    expect('App\Domain\Stores\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('has no legacy Stores Volt view or route', function (): void {
    expect(file_exists(resource_path('views/livewire/admin/settings/stores.blade.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('admin/settings/stores");
});
