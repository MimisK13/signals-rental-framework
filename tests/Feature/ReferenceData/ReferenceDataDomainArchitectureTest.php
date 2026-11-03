<?php

declare(strict_types=1);

arch('reference data presentation does not depend on Volt', function (): void {
    expect('App\Domain\ReferenceData\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('has no legacy Countries Volt view or route', function (): void {
    expect(file_exists(resource_path('views/livewire/admin/settings/countries.blade.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('admin/settings/countries");
});
