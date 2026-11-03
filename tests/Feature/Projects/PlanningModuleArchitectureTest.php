<?php

declare(strict_types=1);

arch('Projects Planning presentation does not depend on Volt', function (): void {
    expect('App\Domain\Projects\Planning\Presentation')->not->toUse('Livewire\Volt');
});

it('keeps planning read models and presentation inside Projects', function (): void {
    expect(file_exists(app_path('Livewire/Planner/Index.php')))->toBeFalse()
        ->and(file_exists(app_path('Services/Planning')))->toBeFalse()
        ->and(file_exists(app_path('Data/Planning')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/planner')))->toBeFalse()
        ->and(route('planner'))->toEndWith('/planner');
});
