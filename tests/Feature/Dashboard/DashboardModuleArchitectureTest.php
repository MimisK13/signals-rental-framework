<?php

declare(strict_types=1);

it('keeps dashboard read models and presentation inside the Dashboard module', function (): void {
    expect(file_exists(app_path('Services/Dashboard/DashboardKpiService.php')))->toBeFalse()
        ->and(file_exists(app_path('Livewire/Dashboard/KpiCards.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/dashboard.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/dashboard/kpi-cards.blade.php')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Route::view('dashboard'");
});

arch('Dashboard presentation is native and does not depend on Volt', function (): void {
    expect('App\Domain\Dashboard\Presentation')->not->toUse('Livewire\Volt');
});
