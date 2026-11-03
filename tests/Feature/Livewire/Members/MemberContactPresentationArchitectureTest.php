<?php

declare(strict_types=1);

arch('member contact presentation does not depend on Volt', function (): void {
    expect('App\Domain\Members\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('has no legacy Members presentation wrappers or Volt views', function (): void {
    expect(is_dir(app_path('Livewire/Members')))->toBeFalse()
        ->and(is_dir(resource_path('views/livewire/members')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('members");
});
