<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

arch('webhooks presentation does not depend on Volt', function (): void {
    expect('App\Domain\Webhooks\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('has no legacy Webhooks Volt view, partial, or routes', function (): void {
    expect(file_exists(resource_path('views/livewire/api/webhooks.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/api/partials/event-picker.blade.php')))->toBeFalse()
        ->and(Route::has('admin.settings.webhooks'))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('api/webhooks")
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Route::post('webhooks/receive");
});
