<?php

declare(strict_types=1);
use App\Domain\Documentation\Presentation\Http\Controllers\DocsController;
use App\Domain\Documentation\Services\DocsService;

it('keeps documentation services and presentation inside the Documentation module', function (): void {
    expect(class_exists(DocsService::class))->toBeTrue()
        ->and(class_exists(DocsController::class))->toBeTrue()
        ->and(file_exists(app_path('Services/DocsService.php')))->toBeFalse()
        ->and(file_exists(app_path('Http/Controllers/Web/DocsController.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/docs')))->toBeFalse()
        ->and(route('docs.index'))->toEndWith('/docs');
});
