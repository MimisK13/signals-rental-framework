<?php

use App\Http\Controllers\Web\DocsController;
use App\Http\Controllers\Web\InboundWebhookController;
use App\Http\Controllers\Web\OpportunityLineItemController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Middleware\VerifyInboundWebhookSignature;
use App\Livewire\Members\Addresses;
use App\Livewire\Members\AddressForm;
use App\Livewire\Members\EmailForm;
use App\Livewire\Members\Emails;
use App\Livewire\Members\LinkForm;
use App\Livewire\Members\Links;
use App\Livewire\Members\PhoneForm;
use App\Livewire\Members\Phones;
use App\Livewire\Planner\Index as PlannerIndex;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::post('webhooks/receive/{source}', InboundWebhookController::class)
    ->middleware(VerifyInboundWebhookSignature::class)
    ->name('webhooks.receive');

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

Route::middleware(['signals.setup-required'])->group(function () {
    Volt::route('setup', 'setup.wizard')->name('setup.wizard');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::view('dashboard', 'dashboard')
    ->middleware(['signals.setup-complete', 'auth', 'verified', '2fa', 'signals.session-timeout'])
    ->name('dashboard');

Route::middleware(['signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/calendar', 'settings.calendar')->name('settings.calendar');
    Volt::route('settings/notifications', 'settings.notifications')->name('settings.notifications');

    // Search
    Route::get('search', SearchController::class)->name('search');

    // API Console
    Volt::route('api', 'api.index')->name('api.index');
    Volt::route('api/explorer', 'api.explorer')->name('api.explorer');
    Volt::route('api/logs', 'api.logs')->name('api.logs');
    Volt::route('api/usage', 'api.usage')->name('api.usage');
    Volt::route('api/webhooks', 'api.webhooks')->name('api.webhooks');

    // Members
    Volt::route('members', 'members.index')->name('members.index');
    Volt::route('members/create', 'members.form')->name('members.create');
    Volt::route('members/{member}', 'members.show')->name('members.show');
    Volt::route('members/{member}/edit', 'members.form')->name('members.edit');
    Route::livewire('members/{member}/addresses', Addresses::class)->name('members.addresses');
    Route::livewire('members/{member}/addresses/create', AddressForm::class)->name('members.addresses.create');
    Route::livewire('members/{member}/addresses/{address}/edit', AddressForm::class)->name('members.addresses.edit');
    Route::livewire('members/{member}/emails', Emails::class)->name('members.emails');
    Route::livewire('members/{member}/emails/create', EmailForm::class)->name('members.emails.create');
    Route::livewire('members/{member}/emails/{email}/edit', EmailForm::class)->name('members.emails.edit');
    Route::livewire('members/{member}/phones', Phones::class)->name('members.phones');
    Route::livewire('members/{member}/phones/create', PhoneForm::class)->name('members.phones.create');
    Route::livewire('members/{member}/phones/{phone}/edit', PhoneForm::class)->name('members.phones.edit');
    Route::livewire('members/{member}/links', Links::class)->name('members.links');
    Route::livewire('members/{member}/links/create', LinkForm::class)->name('members.links.create');
    Route::livewire('members/{member}/links/{link}/edit', LinkForm::class)->name('members.links.edit');
    Volt::route('members/{member}/custom-fields', 'members.custom-fields')->name('members.custom-fields');
    Volt::route('members/{member}/relationships', 'members.relationships')->name('members.relationships');
    Volt::route('members/{member}/relationships/create', 'members.relationship-form')->name('members.relationships.create');
    Volt::route('members/{member}/quotes', 'members.quotes')->name('members.quotes');
    Volt::route('members/{member}/opportunities', 'members.opportunities')->name('members.opportunities');
    Volt::route('members/{member}/invoices', 'members.invoices')->name('members.invoices');
    Volt::route('members/{member}/information', 'members.information')->name('members.information');
    Volt::route('members/{member}/member-contacts', 'members.member-contacts')->name('members.contacts');

    // Products
    Volt::route('products', 'products.index')->name('products.index');
    Volt::route('products/create', 'products.form')->name('products.create');
    Volt::route('products/{product}', 'products.show')->name('products.show');
    Volt::route('products/{product}/financials', 'products.financials')->name('products.financials');
    Volt::route('products/{product}/investment', 'products.investment')->name('products.investment');
    Volt::route('products/{product}/utilization', 'products.utilization')->name('products.utilization');
    Volt::route('products/{product}/inspections', 'products.inspections')->name('products.inspections');
    Volt::route('products/{product}/edit', 'products.form')->name('products.edit');
    Volt::route('products/{product}/stock', 'products.stock')->name('products.stock');
    Volt::route('products/{product}/accessories', 'products.accessories')->name('products.accessories');
    Volt::route('products/{product}/container-config', 'products.container-config')->name('products.container-config');
    Volt::route('products/{product}/custom-fields', 'products.custom-fields')->name('products.custom-fields');

    // Product Groups
    Volt::route('product-groups', 'product-groups.index')->name('product-groups.index');
    // Legacy full-page routes remain as compatibility redirects; create/edit now use the index panels.
    Route::redirect('product-groups/create', '/product-groups')->name('product-groups.create');
    Route::get('product-groups/{productGroup}/edit', fn () => redirect()->route('product-groups.index'))->name('product-groups.edit');

    // Stock Levels

    // Physical serialized asset detail and financial history.

    // Invoices

    // Purchase orders

    // Supplier accounts-payable documents.

    // Virtual stock
    Volt::route('virtual-stock', 'virtual-stock.index')->name('virtual-stock.index');
    Volt::route('virtual-stock/create', 'virtual-stock.form')->name('virtual-stock.create');
    Volt::route('virtual-stock/{virtualStockIntake}', 'virtual-stock.show')->name('virtual-stock.show');

    // Opportunities
    //
    // Named web routes resolve now so route('opportunities.*') works everywhere
    // (global search, member sub-pages, future nav). The full Livewire UI lands
    // in M8 and replaces the placeholder Volt components in place — the route
    // names are stable. 'create' is registered before the '{opportunity}' show
    // route so /opportunities/create is never matched as an opportunity id.
    Volt::route('opportunities', 'opportunities.index')->name('opportunities.index');

    Volt::route('opportunities/create', 'opportunities.form')->name('opportunities.create');
    // The Show page + all of its sub-tabs/edit resolve the {opportunity} binding
    // with ->withTrashed() so ARCHIVED (soft-deleted) opportunities remain viewable
    // (read-only, with a Restore action) rather than 404'ing. Mutating an archived
    // record is blocked at the action layer; the Show header surfaces an archived
    // banner + Restore.
    Volt::route('opportunities/{opportunity}', 'opportunities.show')->name('opportunities.show')->withTrashed();
    Volt::route('opportunities/{opportunity}/edit', 'opportunities.form')->name('opportunities.edit')->withTrashed();
    // Show sub-page tabs (M8-2), mirroring the Products tab convention — one Volt
    // page per tab, each @include-ing the shared header + tabs partials. The Overview
    // page embeds the line-item editor (no standalone "items" route) and the
    // "Versions & Timeline" tab folds in the activity timeline (no standalone
    // "activities" route) — see the show-page restructure. All resolve withTrashed()
    // so an archived opportunity's tabs remain reachable.
    Volt::route('opportunities/{opportunity}/assets', 'opportunities.assets')->name('opportunities.assets')->withTrashed();
    Volt::route('opportunities/{opportunity}/costs', 'opportunities.costs')->name('opportunities.costs')->withTrashed();
    Volt::route('opportunities/{opportunity}/invoices', 'opportunities.invoices')->name('opportunities.invoices')->withTrashed();
    Volt::route('opportunities/{opportunity}/participants', 'opportunities.participants')->name('opportunities.participants')->withTrashed();
    Volt::route('opportunities/{opportunity}/versions', 'opportunities.versions')->name('opportunities.versions')->withTrashed();
    Volt::route('opportunities/{opportunity}/custom-fields', 'opportunities.custom-fields')->name('opportunities.custom-fields')->withTrashed();
    Volt::route('opportunities/{opportunity}/documents', 'opportunities.documents')->name('opportunities.documents')->withTrashed();

    Route::delete('opportunities/{opportunity}/items/{item}', [OpportunityLineItemController::class, 'destroy'])
        ->name('opportunities.items.destroy')
        ->withTrashed();

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
| Invitation Acceptance (signed URL, no auth required)
|--------------------------------------------------------------------------
*/

Route::middleware(['signals.setup-complete'])->group(function () {
    Volt::route('invitation/{user}', 'auth.accept-invitation')
        ->name('invitation.accept')
        ->middleware('signed');
});

/*
|--------------------------------------------------------------------------
| Admin Panel
|--------------------------------------------------------------------------
*/

Route::middleware(['signals.setup-complete', 'auth', '2fa', 'admin', 'signals.session-timeout'])->group(function () {
    Volt::route('admin', 'admin.index')->name('admin.index');
    Route::redirect('admin/settings', 'admin');

    // Account
    Volt::route('admin/settings/company', 'admin.settings.company')->name('admin.settings.company');
    Volt::route('admin/settings/stores', 'admin.settings.stores')->name('admin.settings.stores');
    Volt::route('admin/settings/branding', 'admin.settings.branding')->name('admin.settings.branding');

    // Users & Security
    Volt::route('admin/settings/users', 'admin.settings.users')->name('admin.settings.users');
    Volt::route('admin/settings/users/{user}/edit', 'admin.settings.user-form')->name('admin.settings.users.edit');
    Volt::route('admin/settings/roles', 'admin.settings.roles')->name('admin.settings.roles');
    Volt::route('admin/settings/roles/create', 'admin.settings.role-form')->name('admin.settings.roles.create');
    Volt::route('admin/settings/roles/{role}/edit', 'admin.settings.role-form')->name('admin.settings.roles.edit');
    Volt::route('admin/settings/permissions', 'admin.settings.permissions')->name('admin.settings.permissions');
    Volt::route('admin/settings/security', 'admin.settings.security')->name('admin.settings.security');

    // API
    Volt::route('admin/settings/api', 'admin.settings.api')->name('admin.settings.api');

    // Preferences
    Volt::route('admin/settings/preferences', 'admin.settings.preferences')->name('admin.settings.preferences');
    Volt::route('admin/settings/email', 'admin.settings.email')->name('admin.settings.email');
    Volt::route('admin/settings/notifications', 'admin.settings.notifications')->name('admin.settings.notifications');
    Volt::route('admin/settings/scheduling', 'admin.settings.scheduling')->name('admin.settings.scheduling');

    // Integrations
    Volt::route('admin/settings/integrations', 'admin.settings.integrations')->name('admin.settings.integrations');

    // System
    Volt::route('admin/settings/action-log', 'admin.settings.action-log')->name('admin.settings.action-log');
    Volt::route('admin/settings/system-health', 'admin.settings.system-health')->name('admin.settings.system-health');
    Volt::route('admin/settings/infrastructure', 'admin.settings.infrastructure')->name('admin.settings.infrastructure');
    Volt::route('admin/settings/seeders', 'admin.settings.seeders')->name('admin.settings.seeders');

    // Webhooks (redirected to API Console)
    Route::redirect('admin/settings/webhooks', '/api/webhooks')->name('admin.settings.webhooks');

    // Data
    Volt::route('admin/settings/countries', 'admin.settings.countries')->name('admin.settings.countries');

    Volt::route('admin/settings/finance', 'admin.settings.finance')->name('admin.settings.finance');

    // Pricing

    // Pricing categories
    Volt::route('admin/settings/pricing/discount-categories', 'admin.settings.pricing.discount-categories')->name('admin.settings.pricing.discount-categories');

    // Documents
    Volt::route('admin/settings/numbering', 'admin.settings.numbering')->name('admin.settings.numbering');
    Volt::route('admin/settings/pricing/discount-categories/create', 'admin.settings.pricing.discount-category-form')->name('admin.settings.pricing.discount-categories.create');
    Volt::route('admin/settings/pricing/discount-categories/{discountCategory}/edit', 'admin.settings.pricing.discount-category-form')->name('admin.settings.pricing.discount-categories.edit');
    Volt::route('admin/settings/pricing/price-categories', 'admin.settings.pricing.price-categories')->name('admin.settings.pricing.price-categories');
    Volt::route('admin/settings/pricing/price-categories/create', 'admin.settings.pricing.price-category-form')->name('admin.settings.pricing.price-categories.create');
    Volt::route('admin/settings/pricing/price-categories/{priceCategory}/edit', 'admin.settings.pricing.price-category-form')->name('admin.settings.pricing.price-categories.edit');
    Volt::route('admin/settings/pricing/bundle-rules', 'admin.settings.pricing.bundle-rules')->name('admin.settings.pricing.bundle-rules');
    Volt::route('admin/settings/pricing/bundle-rules/create', 'admin.settings.pricing.bundle-rule-form')->name('admin.settings.pricing.bundle-rules.create');
    Volt::route('admin/settings/pricing/bundle-rules/{bundleRule}/edit', 'admin.settings.pricing.bundle-rule-form')->name('admin.settings.pricing.bundle-rules.edit');
    Volt::route('admin/settings/pricing/surcharge-groups', 'admin.settings.pricing.surcharge-groups')->name('admin.settings.pricing.surcharge-groups');
    Volt::route('admin/settings/pricing/surcharge-groups/create', 'admin.settings.pricing.surcharge-group-form')->name('admin.settings.pricing.surcharge-groups.create');
    Volt::route('admin/settings/pricing/surcharge-groups/{surchargeGroup}/edit', 'admin.settings.pricing.surcharge-group-form')->name('admin.settings.pricing.surcharge-groups.edit');
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

require __DIR__.'/auth.php';
