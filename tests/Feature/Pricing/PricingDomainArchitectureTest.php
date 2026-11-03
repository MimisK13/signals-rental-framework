<?php

declare(strict_types=1);

arch('pricing presentation does not depend on Volt', function (): void {
    expect('App\Domain\Pricing\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('has no legacy Pricing presentation wrappers or Volt routes', function (): void {
    expect(is_dir(resource_path('views/livewire/admin/settings/pricing')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('admin/settings/pricing");
});
