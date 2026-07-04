<?php

use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\Web\CommunicationUnsubscribeController;
use App\Http\Controllers\Web\DocsController;
use App\Http\Controllers\Web\InboundWebhookController;
use App\Http\Controllers\Web\OpportunityLineItemController;
use App\Http\Controllers\Web\PublicDocumentController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Middleware\VerifyInboundWebhookSignature;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::post('webhooks/receive/{source}', InboundWebhookController::class)
    ->middleware(VerifyInboundWebhookSignature::class)
    ->name('webhooks.receive');

Route::prefix(config('documents.public_route_prefix', 'd'))->group(function (): void {
    Route::get('{token}', [PublicDocumentController::class, 'show'])->name('documents.public.show');
    Route::post('{token}/pin', [PublicDocumentController::class, 'verifyPin'])->name('documents.public.verify-pin');
});

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
    Volt::route('members/{member}/addresses', 'members.addresses')->name('members.addresses');
    Volt::route('members/{member}/addresses/create', 'members.address-form')->name('members.addresses.create');
    Volt::route('members/{member}/addresses/{address}/edit', 'members.address-form')->name('members.addresses.edit');
    Volt::route('members/{member}/emails', 'members.emails')->name('members.emails');
    Volt::route('members/{member}/emails/create', 'members.email-form')->name('members.emails.create');
    Volt::route('members/{member}/emails/{email}/edit', 'members.email-form')->name('members.emails.edit');
    Volt::route('members/{member}/phones', 'members.phones')->name('members.phones');
    Volt::route('members/{member}/phones/create', 'members.phone-form')->name('members.phones.create');
    Volt::route('members/{member}/phones/{phone}/edit', 'members.phone-form')->name('members.phones.edit');
    Volt::route('members/{member}/links', 'members.links')->name('members.links');
    Volt::route('members/{member}/links/create', 'members.link-form')->name('members.links.create');
    Volt::route('members/{member}/links/{link}/edit', 'members.link-form')->name('members.links.edit');
    Volt::route('members/{member}/custom-fields', 'members.custom-fields')->name('members.custom-fields');
    Volt::route('members/{member}/relationships', 'members.relationships')->name('members.relationships');
    Volt::route('members/{member}/relationships/create', 'members.relationship-form')->name('members.relationships.create');
    Volt::route('members/{member}/quotes', 'members.quotes')->name('members.quotes');
    Volt::route('members/{member}/opportunities', 'members.opportunities')->name('members.opportunities');
    Volt::route('members/{member}/invoices', 'members.invoices')->name('members.invoices');
    Volt::route('members/{member}/information', 'members.information')->name('members.information');
    Volt::route('members/{member}/member-contacts', 'members.member-contacts')->name('members.contacts');
    Volt::route('members/{member}/activities', 'members.activities')->name('members.activities');
    Volt::route('members/{member}/files', 'members.files')->name('members.files');
    Volt::route('members/{member}/communications', 'members.communications')->name('members.communications');

    // Products
    Volt::route('products', 'products.index')->name('products.index');
    Volt::route('products/create', 'products.form')->name('products.create');
    Volt::route('products/{product}', 'products.show')->name('products.show');
    Volt::route('products/{product}/edit', 'products.form')->name('products.edit');
    Volt::route('products/{product}/stock', 'products.stock')->name('products.stock');
    Volt::route('products/{product}/accessories', 'products.accessories')->name('products.accessories');
    Volt::route('products/{product}/rates', 'products.rates')->name('products.rates');
    Volt::route('products/{product}/rates/create', 'products.rate-form')->name('products.rates.create');
    Volt::route('products/{product}/rates/{rate}/edit', 'products.rate-form')->name('products.rates.edit');
    Volt::route('products/{product}/supplier-costs', 'products.supplier-costs')->name('products.supplier-costs');
    Volt::route('products/{product}/container-config', 'products.container-config')->name('products.container-config');
    Volt::route('products/{product}/custom-fields', 'products.custom-fields')->name('products.custom-fields');
    Volt::route('products/{product}/activities', 'products.activities')->name('products.activities');
    Volt::route('products/{product}/files', 'products.files')->name('products.files');

    // Product Groups
    Volt::route('product-groups', 'product-groups.index')->name('product-groups.index');
    Volt::route('product-groups/create', 'product-groups.form')->name('product-groups.create');
    Volt::route('product-groups/{productGroup}/edit', 'product-groups.form')->name('product-groups.edit');

    // Stock Levels
    Volt::route('stock-levels', 'stock-levels.index')->name('stock-levels.index');
    Volt::route('stock-levels/create', 'stock-levels.form')->name('stock-levels.create');
    Volt::route('stock-levels/{stockLevel}', 'stock-levels.show')->name('stock-levels.show');
    Volt::route('stock-levels/{stockLevel}/edit', 'stock-levels.form')->name('stock-levels.edit');
    Volt::route('stock-levels/{stockLevel}/activities', 'stock-levels.activities')->name('stock-levels.activities');

    // Invoices
    Volt::route('invoices', 'invoices.index')->name('invoices.index');
    Volt::route('invoices/create', 'invoices.form')->name('invoices.create');
    Volt::route('invoices/{invoice}', 'invoices.show')->name('invoices.show');
    Volt::route('invoices/{invoice}/edit', 'invoices.form')->name('invoices.edit');

    // Purchase orders
    Volt::route('purchase-orders', 'purchase-orders.index')->name('purchase-orders.index');
    Volt::route('purchase-orders/create', 'purchase-orders.form')->name('purchase-orders.create');
    Volt::route('purchase-orders/{purchaseOrder}', 'purchase-orders.show')->name('purchase-orders.show');
    Volt::route('purchase-orders/{purchaseOrder}/edit', 'purchase-orders.form')->name('purchase-orders.edit');

    // Virtual stock
    Volt::route('virtual-stock', 'virtual-stock.index')->name('virtual-stock.index');
    Volt::route('virtual-stock/create', 'virtual-stock.form')->name('virtual-stock.create');
    Volt::route('virtual-stock/{virtualStockIntake}', 'virtual-stock.show')->name('virtual-stock.show');

    // Containers (P49 scaffold — full warehouse UI in P55)
    Volt::route('containers', 'containers.index')->name('containers.index');
    Volt::route('containers/{container}', 'containers.show')->name('containers.show');

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
    Volt::route('opportunities/{opportunity}/shortages', 'opportunities.shortages')->name('opportunities.shortages')->withTrashed();
    Volt::route('opportunities/{opportunity}/versions', 'opportunities.versions')->name('opportunities.versions')->withTrashed();
    Volt::route('opportunities/{opportunity}/activities', 'opportunities.activities')->name('opportunities.activities')->withTrashed();
    Volt::route('opportunities/{opportunity}/custom-fields', 'opportunities.custom-fields')->name('opportunities.custom-fields')->withTrashed();
    Volt::route('opportunities/{opportunity}/files', 'opportunities.files')->name('opportunities.files')->withTrashed();
    Volt::route('opportunities/{opportunity}/communications', 'opportunities.communications')->name('opportunities.communications')->withTrashed();
    Volt::route('opportunities/{opportunity}/documents', 'opportunities.documents')->name('opportunities.documents')->withTrashed();

    Route::delete('opportunities/{opportunity}/items/{item}', [OpportunityLineItemController::class, 'destroy'])
        ->name('opportunities.items.destroy')
        ->withTrashed();

    // Availability
    //
    // The standalone Equipment Availability calendar/gantt page (M8-4b). The Volt
    // component calls AvailabilityService directly (the same service layer the
    // API uses) and gates on availability.view; the Job Planning nav link points
    // here.
    Volt::route('availability', 'availability.index')->name('availability.index');

    // Per-product availability / bookings grid (Current RMS "Product Bookings").
    // Summary rows, opportunity demand bars, then serialised asset rows.
    Volt::route('availability/bookings/{product}/{from}/{days}', 'availability.bookings')
        ->name('availability.bookings')
        ->where(['from' => '[0-9]{4}-[0-9]{2}-[0-9]{2}', 'days' => '[0-9]+']);

    // Job Planner
    //
    // A Gantt / Overlap calendar of OPPORTUNITIES (jobs) across dates — one row
    // (or lane-packed cluster) per opportunity, bars split into Delivery / In-Use
    // / Collection bands derived from the lifecycle dates, coloured by workflow
    // status. The Volt component reads the opportunity projection directly and
    // gates on `opportunities.access` (no dedicated planner permission exists yet —
    // it reuses the opportunities area gate, mirroring the Job Planning nav).
    Volt::route('planner', 'planner.index')->name('planner');

    // Activities
    Volt::route('activities', 'activities.index')->name('activities.index');
    Volt::route('activities/create', 'activities.form')->name('activities.create');
    Volt::route('activities/{activity}', 'activities.show')->name('activities.show');
    Volt::route('activities/{activity}/edit', 'activities.form')->name('activities.edit');

    // Calendar
    Volt::route('calendar', 'calendar.index')->name('calendar.index');
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
| Customer communication unsubscribe (signed URL, no auth required)
|--------------------------------------------------------------------------
*/

Route::middleware(['signals.setup-complete', 'signed', 'throttle:'.config('communications.unsubscribe_throttle', '10,1')])->group(function (): void {
    Route::get('communications/unsubscribe/{member}', [CommunicationUnsubscribeController::class, 'show'])
        ->name('communications.unsubscribe.show');
    Route::post('communications/unsubscribe/{member}', [CommunicationUnsubscribeController::class, 'confirm'])
        ->name('communications.unsubscribe.confirm');
});

/*
|--------------------------------------------------------------------------
| Calendar iCal Feeds (signed URL, no auth required)
|--------------------------------------------------------------------------
*/

Route::middleware(['signals.setup-complete'])->group(function () {
    Route::get('calendar/feed.ics', [CalendarFeedController::class, 'global'])
        ->name('calendar.feed.global')
        ->middleware('signed');
    Route::get('calendar/feed/{user}.ics', [CalendarFeedController::class, 'user'])
        ->name('calendar.feed.user')
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

    // Editor Lab — the chosen "local-first" prototype, KEPT as the design
    // reference for the production line-item editor rebuild (RMS-unified model)
    // until that rebuild is confirmed. The three non-chosen prototypes
    // (jquery / sortable-tree / sortablejs) were removed 2026-06-22. NOT a
    // production route: it lives behind the admin gate so only admins/owners can
    // reach the design reference. The live Overview editor (opportunities.items)
    // is untouched.
    Volt::route('opportunities/{opportunity}/editor-lab/local-first', 'opportunities.prototypes.editor-local-first')
        ->name('opportunities.editor-lab.local-first')->withTrashed();

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
    Volt::route('admin/settings/email-templates', 'admin.settings.email-templates')->name('admin.settings.email-templates');
    Volt::route('admin/settings/email-templates/{template}/edit', 'admin.settings.email-template-form')->name('admin.settings.email-templates.edit');
    Volt::route('admin/settings/notifications', 'admin.settings.notifications')->name('admin.settings.notifications');
    Volt::route('admin/settings/communication-log', 'admin.settings.communication-log')->name('admin.settings.communication-log');
    Volt::route('admin/settings/communication-templates/{communicationTemplate}/edit', 'admin.settings.communication-template-editor')->name('admin.settings.communication-templates.edit');
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
    Volt::route('admin/settings/custom-field-groups', 'admin.settings.custom-field-groups')->name('admin.settings.custom-field-groups');
    Volt::route('admin/settings/custom-field-groups/create', 'admin.settings.custom-field-group-form')->name('admin.settings.custom-field-groups.create');
    Volt::route('admin/settings/custom-field-groups/{customFieldGroup}/edit', 'admin.settings.custom-field-group-form')->name('admin.settings.custom-field-groups.edit');
    Volt::route('admin/settings/custom-fields', 'admin.settings.custom-fields')->name('admin.settings.custom-fields');
    Volt::route('admin/settings/custom-fields/create', 'admin.settings.custom-field-form')->name('admin.settings.custom-fields.create');
    Volt::route('admin/settings/custom-fields/{customField}/edit', 'admin.settings.custom-field-form')->name('admin.settings.custom-fields.edit');
    Volt::route('admin/settings/list-names', 'admin.settings.list-names')->name('admin.settings.list-names');
    Volt::route('admin/settings/list-names/create', 'admin.settings.list-name-form')->name('admin.settings.list-names.create');
    Volt::route('admin/settings/list-names/{listName}/edit', 'admin.settings.list-name-form')->name('admin.settings.list-names.edit');
    Volt::route('admin/settings/lists/{listName}', 'admin.settings.lists')->name('admin.settings.lists');
    Volt::route('admin/settings/lists/{listName}/create', 'admin.settings.list-value-form')->name('admin.settings.list-values.create');
    Volt::route('admin/settings/lists/{listName}/{listValue}/edit', 'admin.settings.list-value-form')->name('admin.settings.list-values.edit');
    Volt::route('admin/settings/countries', 'admin.settings.countries')->name('admin.settings.countries');

    // Tax
    Volt::route('admin/settings/tax/product-tax-classes', 'admin.settings.tax.product-tax-classes')->name('admin.settings.tax.product-tax-classes');
    Volt::route('admin/settings/tax/product-tax-classes/create', 'admin.settings.tax.product-tax-class-form')->name('admin.settings.tax.product-tax-classes.create');
    Volt::route('admin/settings/tax/product-tax-classes/{productTaxClass}/edit', 'admin.settings.tax.product-tax-class-form')->name('admin.settings.tax.product-tax-classes.edit');
    Volt::route('admin/settings/tax/organisation-tax-classes', 'admin.settings.tax.organisation-tax-classes')->name('admin.settings.tax.organisation-tax-classes');
    Volt::route('admin/settings/tax/organisation-tax-classes/create', 'admin.settings.tax.organisation-tax-class-form')->name('admin.settings.tax.organisation-tax-classes.create');
    Volt::route('admin/settings/tax/organisation-tax-classes/{organisationTaxClass}/edit', 'admin.settings.tax.organisation-tax-class-form')->name('admin.settings.tax.organisation-tax-classes.edit');

    // Tax Zones & Categories
    Volt::route('admin/settings/finance', 'admin.settings.finance')->name('admin.settings.finance');
    Volt::route('admin/settings/tax/zones', 'admin.settings.tax.zones')->name('admin.settings.tax.zones');
    Volt::route('admin/settings/tax/zones/create', 'admin.settings.tax.zone-form')->name('admin.settings.tax.zones.create');
    Volt::route('admin/settings/tax/zones/{taxZone}/edit', 'admin.settings.tax.zone-form')->name('admin.settings.tax.zones.edit');
    Volt::route('admin/settings/tax/categories', 'admin.settings.tax.categories')->name('admin.settings.tax.categories');
    Volt::route('admin/settings/tax/categories/create', 'admin.settings.tax.category-form')->name('admin.settings.tax.categories.create');
    Volt::route('admin/settings/tax/categories/{taxCategory}/edit', 'admin.settings.tax.category-form')->name('admin.settings.tax.categories.edit');

    // Tax Rates
    Volt::route('admin/settings/tax/rates', 'admin.settings.tax.rates')->name('admin.settings.tax.rates');
    Volt::route('admin/settings/tax/rates/create', 'admin.settings.tax.rate-form')->name('admin.settings.tax.rates.create');
    Volt::route('admin/settings/tax/rates/{taxRate}/edit', 'admin.settings.tax.rate-form')->name('admin.settings.tax.rates.edit');

    // Tax Rules
    Volt::route('admin/settings/tax/rules', 'admin.settings.tax.rules')->name('admin.settings.tax.rules');
    Volt::route('admin/settings/tax/rules/create', 'admin.settings.tax.rule-form')->name('admin.settings.tax.rules.create');
    Volt::route('admin/settings/tax/rules/create-zone', 'admin.settings.tax.zone-rule-form')->name('admin.settings.tax.rules.create-zone');
    Volt::route('admin/settings/tax/rules/{taxRule}/edit', 'admin.settings.tax.rule-form')->name('admin.settings.tax.rules.edit');
    Volt::route('admin/settings/tax/rules/{taxRule}/edit-zone', 'admin.settings.tax.zone-rule-form')->name('admin.settings.tax.rules.edit-zone');

    // Pricing
    Volt::route('admin/settings/rate-definitions', 'admin.settings.rate-definitions')->name('admin.settings.rate-definitions');
    Volt::route('admin/settings/rate-definitions/create', 'admin.settings.rate-definition-form')->name('admin.settings.rate-definitions.create');
    Volt::route('admin/settings/rate-definitions/{rateDefinition}/edit', 'admin.settings.rate-definition-form')->name('admin.settings.rate-definitions.edit');

    // Pricing categories
    Volt::route('admin/settings/pricing/discount-categories', 'admin.settings.pricing.discount-categories')->name('admin.settings.pricing.discount-categories');

    // Documents
    Volt::route('admin/document-templates', 'document-templates.index')->name('admin.document-templates.index');
    Volt::route('admin/document-templates/{documentTemplate}/edit', 'document-templates.edit')->name('admin.document-templates.edit');
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
