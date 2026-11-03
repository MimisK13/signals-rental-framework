<?php

declare(strict_types=1);

arch('virtual stock presentation does not depend on Volt', function (): void {
    expect('App\Domain\VirtualStock\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('has no legacy VirtualStock Volt views or routes', function (): void {
    expect(is_dir(resource_path('views/livewire/virtual-stock')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('virtual-stock");
});
