<?php

use App\Http\Controllers\Web\DocsController;
use App\Http\Controllers\Web\SearchController;
use App\Livewire\Planner\Index as PlannerIndex;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (config('signals.installed') && ! config('signals.setup_complete')) {
        return redirect()->route('setup.wizard');
    }

    if (config('signals.setup_complete')) {
        return auth()->check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }

    return view('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Setup Wizard
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    // Search
    Route::get('search', SearchController::class)->name('search');

    // Stock Levels

    // Physical serialized asset detail and financial history.

    // Invoices

    // Purchase orders

    // Supplier accounts-payable documents.

    // Virtual stock

    // Job Planner
    //
    // A Gantt / Overlap calendar of OPPORTUNITIES (jobs) across dates — one row
    // (or lane-packed cluster) per opportunity, bars split into Delivery / In-Use
    // / Collection bands derived from the lifecycle dates, coloured by workflow
    // status. The Volt component reads the opportunity projection directly and
    // gates on `opportunities.access` (no dedicated planner permission exists yet —
    // it reuses the opportunities area gate, mirroring the Job Planning nav).
    Route::get('planner', PlannerIndex::class)->name('planner');

    // Calendar package sandbox
    Route::view('calendar/sandbox', 'calendar.sandbox')->name('calendar.sandbox');

    // Dense UI foundation sandbox
    Route::view('dense/sandbox', 'dense.sandbox')->name('dense.sandbox');
});
/*
|--------------------------------------------------------------------------
| Well-Known URLs
|--------------------------------------------------------------------------
|
| Password managers (1Password, Chrome, Safari) deep-link to this URL to
| jump users to the change-password screen. The target route handles its
| own authentication, so this redirect stays publicly reachable.
*/

Route::get('.well-known/change-password', function () {
    return redirect()->route('settings.password');
})->name('well-known.change-password');

/*
|--------------------------------------------------------------------------
| Admin Panel
|--------------------------------------------------------------------------
*/

Route::middleware(['signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function () {
    Volt::route('admin', 'admin.index')->name('admin.index');
    Route::redirect('admin/settings', 'admin');

    // System
    // Data

    // Pricing

    // Pricing categories

    // Documents
});

/*
|--------------------------------------------------------------------------
| Prototypes
|--------------------------------------------------------------------------
*/

Route::prefix('prototypes')->group(function () {
    Volt::route('/', 'prototypes.index')
        ->name('prototypes.index');
    Volt::route('document-editor', 'prototypes.document-editor')
        ->name('prototypes.document-editor');
    Volt::route('grid', 'prototypes.grid')
        ->middleware(['signals.setup-complete', 'auth'])
        ->name('prototypes.grid');
    Volt::route('availability', 'prototypes.availability')
        ->name('prototypes.availability');
    Volt::route('availability-opportunity', 'prototypes.availability-opportunity')
        ->name('prototypes.availability-opportunity');
    Volt::route('workflow-editor', 'prototypes.workflow-editor')
        ->name('prototypes.workflow-editor');
    Volt::route('workflow-editor-split', 'prototypes.workflow-editor-split')
        ->name('prototypes.workflow-editor-split');
    Volt::route('workflow-editor-timeline', 'prototypes.workflow-editor-timeline')
        ->name('prototypes.workflow-editor-timeline');
    Volt::route('workflow-editor-minimal', 'prototypes.workflow-editor-minimal')
        ->name('prototypes.workflow-editor-minimal');
    Volt::route('field-registry', 'prototypes.field-registry')
        ->name('prototypes.field-registry');
    Volt::route('notification-admin', 'prototypes.notification-admin')
        ->name('prototypes.notification-admin');
    Volt::route('rate-engine', 'prototypes.rate-engine')
        ->name('prototypes.rate-engine');
    Volt::route('reporting', 'prototypes.reporting')
        ->name('prototypes.reporting');
    Volt::route('custom-views', 'prototypes.custom-views')
        ->name('prototypes.custom-views');
    Volt::route('import-export', 'prototypes.import-export')
        ->name('prototypes.import-export');
    Volt::route('permissions', 'prototypes.permissions')
        ->name('prototypes.permissions');
    Volt::route('settings-admin', 'prototypes.settings-admin')
        ->name('prototypes.settings-admin');
    Volt::route('shortage-resolution', 'prototypes.shortage-resolution')
        ->name('prototypes.shortage-resolution');
    Volt::route('plugin-system', 'prototypes.plugin-system')
        ->name('prototypes.plugin-system');
    Volt::route('opportunity-lifecycle', 'prototypes.opportunity-lifecycle')
        ->name('prototypes.opportunity-lifecycle');
    Route::redirect('component-reference', '/docs/development/library')
        ->name('prototypes.component-reference');
});

/*
|--------------------------------------------------------------------------
| Social / Promo Pages
|--------------------------------------------------------------------------
*/

Route::prefix('social')->group(function () {
    Route::view('/', 'social.index')->name('social.index');
    Route::view('api-promo', 'social.api-promo')->name('social.api-promo');
    Route::view('admin-promo', 'social.admin-promo')->name('social.admin-promo');
    Route::view('members-promo', 'social.members-promo')->name('social.members-promo');
    Route::view('permissions-promo', 'social.permissions-promo')->name('social.permissions-promo');
    Route::view('email-promo', 'social.email-promo')->name('social.email-promo');
    Route::view('health-promo', 'social.health-promo')->name('social.health-promo');
});

/*
|--------------------------------------------------------------------------
| Documentation
|--------------------------------------------------------------------------
*/

Route::prefix('docs')->group(function () {
    Route::get('/', [DocsController::class, 'index'])->name('docs.index');
    Route::get('changelog', [DocsController::class, 'changelog'])->name('docs.changelog');
    Route::get('sitemap.xml', [DocsController::class, 'sitemap'])->name('docs.sitemap');
    Route::get('robots.txt', [DocsController::class, 'robots'])->name('docs.robots');
    Route::redirect('getting-started/sso', '/docs/getting-started/authentication', 301);
    Route::redirect('getting-started/magic-link', '/docs/getting-started/authentication', 301);
    Route::get('images/{path}', [DocsController::class, 'image'])
        ->name('docs.image')
        ->where('path', '.+');
    Route::get('{section}/{page}', [DocsController::class, 'show'])
        ->name('docs.show')
        ->where(['section' => '[a-z0-9-]+', 'page' => '[a-z0-9-]+']);
});
