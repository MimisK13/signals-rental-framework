<?php

declare(strict_types=1);

use App\Domain\Documentation\Presentation\Http\Controllers\DocsController;
use Illuminate\Support\Facades\Route;

Route::prefix('docs')->middleware('web')->group(function (): void {
    Route::get('/', [DocsController::class, 'index'])->name('docs.index');
    Route::get('changelog', [DocsController::class, 'changelog'])->name('docs.changelog');
    Route::get('sitemap.xml', [DocsController::class, 'sitemap'])->name('docs.sitemap');
    Route::get('robots.txt', [DocsController::class, 'robots'])->name('docs.robots');
    Route::redirect('getting-started/sso', '/docs/getting-started/authentication', 301);
    Route::redirect('getting-started/magic-link', '/docs/getting-started/authentication', 301);
    Route::get('images/{path}', [DocsController::class, 'image'])->name('docs.image')->where('path', '.+');
    Route::get('{section}/{page}', [DocsController::class, 'show'])
        ->name('docs.show')
        ->where(['section' => '[a-z0-9-]+', 'page' => '[a-z0-9-]+']);
});
