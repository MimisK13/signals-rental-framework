<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

arch('API presentation does not depend on Volt', function (): void {
    expect('App\Domain\API\Presentation\Livewire')->not->toUse('Livewire\Volt');
});

it('keeps shared API infrastructure inside the API domain', function (): void {
    expect(file_exists(app_path('Services/Api')))->toBeFalse()
        ->and(file_exists(app_path('Queries/Api')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/api')))->toBeFalse()
        ->and(file_exists(resource_path('views/components/api')))->toBeFalse()
        ->and(file_get_contents(base_path('routes/web.php')))->not->toContain("Volt::route('api")
        ->and(file_exists(base_path('routes/api.php')))->toBeFalse();
});

it('keeps every API migration scoped to one table', function (): void {
    foreach (File::files(app_path('Domain/API/Database/Migrations')) as $migration) {
        preg_match_all('/Schema::create\(/', $migration->getContents(), $matches);

        expect($matches[0])->toHaveCount(1, "{$migration->getFilename()} must create exactly one table.");
    }
});
