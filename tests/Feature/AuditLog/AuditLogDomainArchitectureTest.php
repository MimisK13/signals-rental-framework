<?php

declare(strict_types=1);

arch('audit log presentation does not depend on Volt', function (): void {
    expect('App\Domain\AuditLog\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('has no legacy AuditLog Volt view or route', function (): void {
    expect(file_exists(resource_path('views/livewire/admin/settings/action-log.blade.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('admin/settings/action-log");
});
