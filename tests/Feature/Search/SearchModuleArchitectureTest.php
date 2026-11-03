<?php

declare(strict_types=1);
use App\Domain\Search\Presentation\Http\Controllers\SearchController;

it('keeps cross-domain search inside the Search read-model module', function (): void {
    expect(class_exists(SearchController::class))->toBeTrue()
        ->and(file_exists(app_path('Http/Controllers/Web/SearchController.php')))->toBeFalse()
        ->and(route('search'))->toEndWith('/search');
});
