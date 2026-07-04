<?php

use App\Http\Controllers\Api\V1\AccessoryController;
use App\Http\Controllers\Api\V1\ActionLogController;
use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BundleRuleController;
use App\Http\Controllers\Api\V1\ChannelProviderController;
use App\Http\Controllers\Api\V1\CommunicationLogController;
use App\Http\Controllers\Api\V1\CommunicationTemplateController;
use App\Http\Controllers\Api\V1\ContainerController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\CustomFieldController;
use App\Http\Controllers\Api\V1\CustomFieldGroupController;
use App\Http\Controllers\Api\V1\CustomViewController;
use App\Http\Controllers\Api\V1\DiscountCategoryController;
use App\Http\Controllers\Api\V1\DiscountRuleController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentSequenceController;
use App\Http\Controllers\Api\V1\DocumentTemplateController;
use App\Http\Controllers\Api\V1\DocumentTypeController;
use App\Http\Controllers\Api\V1\EndpointCatalogueController;
use App\Http\Controllers\Api\V1\ExchangeRateController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ListNameController;
use App\Http\Controllers\Api\V1\ListValueController;
use App\Http\Controllers\Api\V1\MemberAddressController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MemberEmailController;
use App\Http\Controllers\Api\V1\MemberLinkController;
use App\Http\Controllers\Api\V1\MemberNotificationPreferenceController;
use App\Http\Controllers\Api\V1\MemberPhoneController;
use App\Http\Controllers\Api\V1\MemberRelationshipController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\NotificationSettingController;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\OpportunityInvoiceController;
use App\Http\Controllers\Api\V1\OpportunityVersionController;
use App\Http\Controllers\Api\V1\OrganisationTaxClassController;
use App\Http\Controllers\Api\V1\PriceCategoryController;
use App\Http\Controllers\Api\V1\ProductContainerTemplateController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductGroupController;
use App\Http\Controllers\Api\V1\ProductRateController;
use App\Http\Controllers\Api\V1\ProductSupplierCostController;
use App\Http\Controllers\Api\V1\ProductTaxClassController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\RateCalculationController;
use App\Http\Controllers\Api\V1\RateDefinitionController;
use App\Http\Controllers\Api\V1\RateEngineMetaController;
use App\Http\Controllers\Api\V1\RequestLogController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SchemaController;
use App\Http\Controllers\Api\V1\SerialisedComponentController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\ShortageController;
use App\Http\Controllers\Api\V1\StockLevelController;
use App\Http\Controllers\Api\V1\StockTransactionController;
use App\Http\Controllers\Api\V1\SurchargeController;
use App\Http\Controllers\Api\V1\SurchargeGroupController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\TaxCategoryController;
use App\Http\Controllers\Api\V1\TaxRateController;
use App\Http\Controllers\Api\V1\TaxRuleController;
use App\Http\Controllers\Api\V1\TaxZoneController;
use App\Http\Controllers\Api\V1\UsageController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VirtualStockIntakeController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are prefixed with /api automatically by Laravel.
| The v1 group adds the /v1 prefix and applies authentication +
| active-user middleware to all endpoints.
|
*/

Route::prefix('v1')->middleware([ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])->group(function (): void {

    // System
    Route::get('system/health', [SystemController::class, 'health'])->name('api.v1.system.health');

    // Schema Discovery
    Route::get('schema', [SchemaController::class, 'index'])->name('api.v1.schema.index');
    Route::get('schema/{model}', [SchemaController::class, 'show'])->name('api.v1.schema.show');

    // Document type field browser
    Route::get('document_types', [DocumentTypeController::class, 'index'])->name('api.v1.document_types.index');
    Route::get('document_types/{type}/fields', [DocumentTypeController::class, 'fields'])->name('api.v1.document_types.fields');
    Route::get('document_sequences', [DocumentSequenceController::class, 'index'])->name('api.v1.document_sequences.index');
    Route::put('document_sequences/{documentSequence}', [DocumentSequenceController::class, 'update'])->name('api.v1.document_sequences.update');
    Route::post('document_templates/import', [DocumentTemplateController::class, 'import'])->name('api.v1.document_templates.import');
    Route::get('document_templates/{documentTemplate}/export', [DocumentTemplateController::class, 'export'])->name('api.v1.document_templates.export');
    Route::get('document_templates', [DocumentTemplateController::class, 'index'])->name('api.v1.document_templates.index');
    Route::post('document_templates', [DocumentTemplateController::class, 'store'])->name('api.v1.document_templates.store');
    Route::get('document_templates/{documentTemplate}', [DocumentTemplateController::class, 'show'])->name('api.v1.document_templates.show');
    Route::put('document_templates/{documentTemplate}', [DocumentTemplateController::class, 'update'])->name('api.v1.document_templates.update');
    Route::delete('document_templates/{documentTemplate}', [DocumentTemplateController::class, 'destroy'])->name('api.v1.document_templates.destroy');
    Route::post('document_templates/{documentTemplate}/set_default', [DocumentTemplateController::class, 'setDefault'])->name('api.v1.document_templates.set_default');
    Route::post('document_templates/{documentTemplate}/duplicate', [DocumentTemplateController::class, 'duplicate'])->name('api.v1.document_templates.duplicate');
    Route::post('document_templates/{documentTemplate}/restore_default', [DocumentTemplateController::class, 'restoreDefault'])->name('api.v1.document_templates.restore_default');
    Route::post('document_templates/{documentTemplate}/versions/{version}/restore', [DocumentTemplateController::class, 'restoreVersion'])->name('api.v1.document_templates.restore_version');

    Route::apiResource('communication_log', CommunicationLogController::class)->only(['index', 'show'])->names('api.v1.communication_log');
    Route::get('communication_templates/{communicationTemplate}/versions', [CommunicationTemplateController::class, 'versions'])->name('api.v1.communication_templates.versions');
    Route::post('communication_templates/{communicationTemplate}/versions/{version}/restore', [CommunicationTemplateController::class, 'restoreVersion'])->name('api.v1.communication_templates.restore_version');
    Route::apiResource('communication_templates', CommunicationTemplateController::class)->names('api.v1.communication_templates');

    Route::get('notification_preferences', [NotificationPreferenceController::class, 'index'])->name('api.v1.notification_preferences.index');
    Route::put('notification_preferences', [NotificationPreferenceController::class, 'update'])->name('api.v1.notification_preferences.update');
    Route::get('members/{member}/notification_preferences', [MemberNotificationPreferenceController::class, 'index'])->name('api.v1.members.notification_preferences.index');
    Route::put('members/{member}/notification_preferences', [MemberNotificationPreferenceController::class, 'update'])->name('api.v1.members.notification_preferences.update');

    Route::post('channel_providers/{channel_provider}/test', [ChannelProviderController::class, 'test'])->name('api.v1.channel_providers.test');
    Route::apiResource('channel_providers', ChannelProviderController::class)->names('api.v1.channel_providers');

    Route::get('notification_settings', [NotificationSettingController::class, 'index'])->name('api.v1.notification_settings.index');
    Route::put('notification_settings', [NotificationSettingController::class, 'update'])->name('api.v1.notification_settings.update');

    Route::get('documents', [DocumentController::class, 'index'])->name('api.v1.documents.index');
    Route::post('documents/generate', [DocumentController::class, 'generate'])->name('api.v1.documents.generate');
    Route::post('documents/batch_generate', [DocumentController::class, 'batchGenerate'])->name('api.v1.documents.batch_generate');
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('api.v1.documents.show');
    Route::post('documents/{document}/finalise', [DocumentController::class, 'finalise'])->name('api.v1.documents.finalise');
    Route::post('documents/{document}/void', [DocumentController::class, 'void'])->name('api.v1.documents.void');
    Route::post('documents/{document}/public_link', [DocumentController::class, 'createPublicLink'])->name('api.v1.documents.public_link.create');
    Route::put('documents/{document}/public_link', [DocumentController::class, 'configurePublicLink'])->name('api.v1.documents.public_link.configure');
    Route::delete('documents/{document}/public_link', [DocumentController::class, 'revokePublicLink'])->name('api.v1.documents.public_link.revoke');

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('api.v1.settings.index');
    Route::get('settings/{group}', [SettingsController::class, 'show'])->name('api.v1.settings.show');
    Route::put('settings/{group}', [SettingsController::class, 'update'])->name('api.v1.settings.update');

    // Users
    Route::apiResource('users', UserController::class)->names('api.v1.users');

    // Roles
    Route::apiResource('roles', RoleController::class)->names('api.v1.roles');

    // Action Log
    Route::get('actions', [ActionLogController::class, 'index'])->name('api.v1.actions.index');
    Route::post('actions/export', [ActionLogController::class, 'export'])->name('api.v1.actions.export');

    // API Console — usage, request logs, endpoint catalogue
    Route::get('usage/export', [UsageController::class, 'export'])->name('api.v1.usage.export');
    Route::get('usage', [UsageController::class, 'index'])->name('api.v1.usage.index');
    Route::get('request_logs', [RequestLogController::class, 'index'])->name('api.v1.request_logs.index');
    Route::get('endpoints', [EndpointCatalogueController::class, 'index'])->name('api.v1.endpoints.index');

    // Webhooks
    Route::apiResource('webhooks', WebhookController::class)->names('api.v1.webhooks');
    Route::get('webhooks/{webhook}/logs', [WebhookController::class, 'logs'])->name('api.v1.webhooks.logs');

    // Members
    Route::apiResource('members', MemberController::class)->names('api.v1.members');
    Route::post('members/{member}/merge', [MemberController::class, 'merge'])->name('api.v1.members.merge');
    Route::post('members/{member}/anonymise', [MemberController::class, 'anonymise'])->name('api.v1.members.anonymise');
    Route::post('members/{member}/statement/email', [MemberController::class, 'emailStatement'])->name('api.v1.members.statement.email');
    Route::post('members/{member}/restore', [MemberController::class, 'restore'])
        ->withTrashed()
        ->name('api.v1.members.restore');
    Route::apiResource('members.addresses', MemberAddressController::class)->except(['show'])->names('api.v1.members.addresses');
    Route::apiResource('members.emails', MemberEmailController::class)->except(['show'])->names('api.v1.members.emails');
    Route::apiResource('members.phones', MemberPhoneController::class)->except(['show'])->names('api.v1.members.phones');
    Route::apiResource('members.links', MemberLinkController::class)->except(['show'])->names('api.v1.members.links');
    Route::apiResource('members.relationships', MemberRelationshipController::class)->only(['index', 'store', 'destroy'])->names('api.v1.members.relationships');
    Route::get('members/{member}/attachments', [AttachmentController::class, 'indexForMember'])->name('api.v1.members.attachments.index');

    // Custom Views
    Route::apiResource('custom_views', CustomViewController::class)->names('api.v1.custom_views');
    Route::post('custom_views/{custom_view}/clone', [CustomViewController::class, 'clone'])->name('api.v1.custom_views.clone');

    // Countries (read-only)
    Route::apiResource('countries', CountryController::class)->only(['index', 'show'])->names('api.v1.countries');

    // Custom Fields
    Route::apiResource('custom_field_groups', CustomFieldGroupController::class)->names('api.v1.custom_field_groups');
    Route::apiResource('custom_fields', CustomFieldController::class)->names('api.v1.custom_fields');

    // Lists of Values
    Route::apiResource('list_names', ListNameController::class)->names('api.v1.list_names');
    Route::apiResource('list_names.list_values', ListValueController::class)->except(['show'])->names('api.v1.list_names.list_values');

    // RMS-compatible alias for Lists of Values
    Route::apiResource('list_of_values', ListNameController::class)->parameters(['list_of_values' => 'list_name'])->names('api.v1.list_of_values');
    Route::apiResource('list_of_values.list_values', ListValueController::class)->parameters(['list_of_values' => 'list_name', 'list_values' => 'list_value'])->except(['show'])->names('api.v1.list_of_values.list_values');

    // Tax Classes
    Route::apiResource('organisation_tax_classes', OrganisationTaxClassController::class)->names('api.v1.organisation_tax_classes');
    Route::apiResource('product_tax_classes', ProductTaxClassController::class)->names('api.v1.product_tax_classes');

    // Pricing Categories
    Route::apiResource('discount_categories', DiscountCategoryController::class)->names('api.v1.discount_categories');
    Route::apiResource('discount_rules', DiscountRuleController::class)->names('api.v1.discount_rules');
    Route::apiResource('bundle_rules', BundleRuleController::class)->names('api.v1.bundle_rules');
    Route::apiResource('surcharge_groups', SurchargeGroupController::class)->names('api.v1.surcharge_groups');
    Route::apiResource('surcharges', SurchargeController::class)->names('api.v1.surcharges');
    Route::apiResource('price_categories', PriceCategoryController::class)->names('api.v1.price_categories');

    // Tax Rates & Rules
    Route::apiResource('tax_zones', TaxZoneController::class)->names('api.v1.tax_zones');
    Route::apiResource('tax_categories', TaxCategoryController::class)->names('api.v1.tax_categories');
    Route::apiResource('tax_rates', TaxRateController::class)->names('api.v1.tax_rates');
    Route::apiResource('tax_rules', TaxRuleController::class)->names('api.v1.tax_rules');

    // Currencies (read-only)
    Route::apiResource('currencies', CurrencyController::class)->only(['index', 'show'])->names('api.v1.currencies');

    // Exchange Rates
    Route::apiResource('exchange_rates', ExchangeRateController::class)->names('api.v1.exchange_rates');

    // Attachments
    Route::apiResource('attachments', AttachmentController::class)->only(['show', 'store', 'destroy'])->names('api.v1.attachments');

    // Products
    Route::apiResource('products', ProductController::class)->names('api.v1.products');
    Route::post('products/{product}/merge', [ProductController::class, 'merge'])->name('api.v1.products.merge');
    Route::apiResource('products.accessories', AccessoryController::class)->only(['index', 'store', 'update', 'destroy'])->names('api.v1.products.accessories');
    Route::apiResource('products.rates', ProductRateController::class)->names('api.v1.products.rates');
    Route::post('products/{product}/supplier_costs/{supplierCost}/set_preferred', [ProductSupplierCostController::class, 'setPreferred'])
        ->name('api.v1.products.supplier_costs.set_preferred');
    Route::apiResource('products.supplier_costs', ProductSupplierCostController::class)
        ->parameters(['supplier_costs' => 'supplierCost'])
        ->names('api.v1.products.supplier_costs');
    Route::post('products/{product}/calculate_rate', [RateCalculationController::class, 'calculate'])->name('api.v1.products.calculate_rate');
    // Kit composition (serialised_components — the product's bill-of-materials)
    Route::apiResource('products.components', SerialisedComponentController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['components' => 'component'])
        ->names('api.v1.products.components');
    Route::get('products/{product}/container_template', [ProductContainerTemplateController::class, 'show'])
        ->name('api.v1.products.container_template.show');
    Route::put('products/{product}/container_template', [ProductContainerTemplateController::class, 'update'])
        ->name('api.v1.products.container_template.update');

    // Product Groups
    Route::apiResource('product_groups', ProductGroupController::class)->names('api.v1.product_groups');

    // Stock Levels
    Route::apiResource('stock_levels', StockLevelController::class)->names('api.v1.stock_levels');

    // Containers — routes bind by public UUID ({@see Container::resolveRouteBinding})
    // with integer-id fallback for backward compatibility. The pack/unpack and
    // temporary create routes are declared before the {container} segment routes.
    Route::post('containers/temporary', [ContainerController::class, 'storeTemporary'])->name('api.v1.containers.temporary.store');
    Route::post('containers/repack/start', [ContainerController::class, 'startRepack'])->name('api.v1.containers.repack.start');
    Route::post('containers/{container}/pack', [ContainerController::class, 'pack'])->name('api.v1.containers.pack');
    Route::post('containers/{container}/transfer', [ContainerController::class, 'transfer'])->name('api.v1.containers.transfer');
    Route::post('containers/{container}/unpack', [ContainerController::class, 'unpack'])->name('api.v1.containers.unpack');
    Route::post('containers/{container}/seal', [ContainerController::class, 'seal'])->name('api.v1.containers.seal');
    Route::post('containers/{container}/unseal', [ContainerController::class, 'unseal'])->name('api.v1.containers.unseal');
    Route::post('containers/{container}/dissolve', [ContainerController::class, 'dissolve'])->name('api.v1.containers.dissolve');
    Route::post('containers/{container}/dispatch', [ContainerController::class, 'dispatch'])->name('api.v1.containers.dispatch');
    Route::post('containers/{container}/return', [ContainerController::class, 'returnKit'])->name('api.v1.containers.return');
    Route::post('containers/{container}/repack', [ContainerController::class, 'repackItem'])->name('api.v1.containers.repack.item');
    Route::post('containers/{container}/verify-checkin', [ContainerController::class, 'verifyCheckin'])->name('api.v1.containers.verify-checkin');
    Route::get('containers/{container}/compliance', [ContainerController::class, 'compliance'])->name('api.v1.containers.compliance');
    Route::get('containers', [ContainerController::class, 'index'])->name('api.v1.containers.index');
    Route::post('containers', [ContainerController::class, 'store'])->name('api.v1.containers.store');
    Route::get('containers/{container}', [ContainerController::class, 'show'])->name('api.v1.containers.show');
    Route::patch('containers/{container}', [ContainerController::class, 'update'])->name('api.v1.containers.update');

    // Availability (read-only: point query via ?date, range query via ?from&?to).
    // The static calendar/shortages grids and the {product}/gantt bar view are
    // declared before the flat index so the literal segments win the route match.
    Route::get('availability/calendar', [AvailabilityController::class, 'calendar'])->name('api.v1.availability.calendar');
    Route::get('availability/shortages', [AvailabilityController::class, 'shortages'])->name('api.v1.availability.shortages');
    Route::get('availability/{product}/gantt', [AvailabilityController::class, 'gantt'])->name('api.v1.availability.gantt');
    Route::get('availability', [AvailabilityController::class, 'index'])->name('api.v1.availability.index');
    Route::get('products/{product}/availability', [AvailabilityController::class, 'showForProduct'])->name('api.v1.products.availability');
    // Serialised assets of a product free across ?from&?to at ?store_id.
    Route::get('products/{product}/available-assets', [AvailabilityController::class, 'availableAssets'])->name('api.v1.products.available_assets');

    // Stock Transactions (nested under products/stock_levels, matching RMS)
    Route::get('products/{product}/stock_levels/{stock_level}/stock_transactions', [StockTransactionController::class, 'index'])->name('api.v1.stock_transactions.index');
    Route::get('products/{product}/stock_levels/{stock_level}/stock_transactions/{stock_transaction}', [StockTransactionController::class, 'show'])->name('api.v1.stock_transactions.show');
    Route::post('products/{product}/stock_levels/{stock_level}/stock_transactions', [StockTransactionController::class, 'store'])->name('api.v1.stock_transactions.store');
    Route::delete('products/{product}/stock_levels/{stock_level}/stock_transactions/{stock_transaction}', [StockTransactionController::class, 'destroy'])->name('api.v1.stock_transactions.destroy');

    // Activities
    Route::apiResource('activities', ActivityController::class)->names('api.v1.activities');
    Route::post('activities/{activity}/complete', [ActivityController::class, 'complete'])->name('api.v1.activities.complete');

    // Opportunities (event-sourced lifecycle)
    // Read sub-resources (assets / availability / activity) — declared before the
    // apiResource so the explicit GET routes win.
    Route::get('opportunities/{opportunity}/assets', [OpportunityController::class, 'assets'])->name('api.v1.opportunities.assets');
    Route::get('opportunities/{opportunity}/availability', [OpportunityController::class, 'availability'])->name('api.v1.opportunities.availability');
    Route::get('opportunities/{opportunity}/activity', [OpportunityController::class, 'activity'])->name('api.v1.opportunities.activity');
    // Guard-aware action availability for the Show toolbar (dry-run, no side effects).
    Route::get('opportunities/{opportunity}/available_actions', [OpportunityController::class, 'availableActions'])->name('api.v1.opportunities.available_actions');
    Route::post('opportunities/{opportunity}/clone', [OpportunityController::class, 'clone'])->name('api.v1.opportunities.clone');
    Route::post('opportunities/{opportunity}/convert_to_quotation', [OpportunityController::class, 'convertToQuotation'])->name('api.v1.opportunities.convert_to_quotation');
    Route::post('opportunities/{opportunity}/convert_to_order', [OpportunityController::class, 'convertToOrder'])->name('api.v1.opportunities.convert_to_order');
    // Backward-transition lifecycle moves (routed through the guard pipeline).
    Route::post('opportunities/{opportunity}/reinstate', [OpportunityController::class, 'reinstate'])->name('api.v1.opportunities.reinstate');
    Route::post('opportunities/{opportunity}/revert_to_quotation', [OpportunityController::class, 'revertToQuotation'])->name('api.v1.opportunities.revert_to_quotation');
    Route::post('opportunities/{opportunity}/revert_to_draft', [OpportunityController::class, 'revertToDraft'])->name('api.v1.opportunities.revert_to_draft');
    Route::post('opportunities/{opportunity}/reopen', [OpportunityController::class, 'reopen'])->name('api.v1.opportunities.reopen');
    // Restore (un-archive) a soft-deleted opportunity — the binding resolves the
    // trashed projection row (mirrors members/{member}/restore).
    Route::post('opportunities/{opportunity}/restore', [OpportunityController::class, 'restore'])
        ->withTrashed()
        ->name('api.v1.opportunities.restore');
    Route::post('opportunities/{opportunity}/lock_locks', [OpportunityController::class, 'lockLocks'])->name('api.v1.opportunities.lock_locks');
    Route::post('opportunities/{opportunity}/unlock_locks', [OpportunityController::class, 'unlockLocks'])->name('api.v1.opportunities.unlock_locks');
    Route::post('opportunities/{opportunity}/change_status', [OpportunityController::class, 'changeStatus'])->name('api.v1.opportunities.change_status');
    // Line items (priced via the rate + tax engines; totals roll up to the parent)
    Route::post('opportunities/{opportunity}/items', [OpportunityController::class, 'storeItem'])->name('api.v1.opportunities.items.store');
    Route::patch('opportunities/{opportunity}/items/tree', [OpportunityController::class, 'restructureItemsTree'])->name('api.v1.opportunities.items.tree');
    // Per-asset allocation sub-resource (M5). Declared before items/{item} PATCH so
    // the explicit asset routes win.
    Route::post('opportunities/{opportunity}/items/{item}/assets', [OpportunityController::class, 'storeAsset'])->name('api.v1.opportunities.items.assets.store');
    Route::patch('opportunities/{opportunity}/items/{item}/assets/{asset}', [OpportunityController::class, 'updateAsset'])->name('api.v1.opportunities.items.assets.update');
    Route::delete('opportunities/{opportunity}/items/{item}/assets/{asset}', [OpportunityController::class, 'destroyAsset'])->name('api.v1.opportunities.items.assets.destroy');
    // Bulk-line dispatch/return/adjust (M5-2 — non-serialised lines). Declared
    // before items/{item} PATCH so the explicit fulfilment route wins.
    Route::patch('opportunities/{opportunity}/items/{item}/fulfilment', [OpportunityController::class, 'updateBulkQuantity'])->name('api.v1.opportunities.items.fulfilment');
    Route::patch('opportunities/{opportunity}/items/{item}', [OpportunityController::class, 'updateItem'])->name('api.v1.opportunities.items.update');
    Route::delete('opportunities/{opportunity}/items/{item}', [OpportunityController::class, 'destroyItem'])->name('api.v1.opportunities.items.destroy');
    // Batch asset allocation (RMS quick_allocate).
    Route::post('opportunities/{opportunity}/quick_allocate', [OpportunityController::class, 'quickAllocate'])->name('api.v1.opportunities.quick_allocate');
    // Batch asset preparation (RMS quick_prepare).
    Route::post('opportunities/{opportunity}/quick_prepare', [OpportunityController::class, 'quickPrepare'])->name('api.v1.opportunities.quick_prepare');
    // Batch dispatch/return (RMS quick_book_out / quick_check_in).
    Route::post('opportunities/{opportunity}/quick_book_out', [OpportunityController::class, 'quickBookOut'])->name('api.v1.opportunities.quick_book_out');
    Route::post('opportunities/{opportunity}/quick_check_in', [OpportunityController::class, 'quickCheckIn'])->name('api.v1.opportunities.quick_check_in');
    // Ad-hoc costs (taxed, not rate-priced; totals roll up to the parent)
    Route::post('opportunities/{opportunity}/costs', [OpportunityController::class, 'storeCost'])->name('api.v1.opportunities.costs.store');
    Route::patch('opportunities/{opportunity}/costs/{cost}', [OpportunityController::class, 'updateCost'])->name('api.v1.opportunities.costs.update');
    Route::delete('opportunities/{opportunity}/costs/{cost}', [OpportunityController::class, 'destroyCost'])->name('api.v1.opportunities.costs.destroy');
    // Participants (members in a named role; plain CRM associations, RMS participants[])
    Route::post('opportunities/{opportunity}/participants', [OpportunityController::class, 'storeParticipant'])->name('api.v1.opportunities.participants.store');
    Route::patch('opportunities/{opportunity}/participants/{participant}', [OpportunityController::class, 'updateParticipant'])->name('api.v1.opportunities.participants.update');
    Route::delete('opportunities/{opportunity}/participants/{participant}', [OpportunityController::class, 'destroyParticipant'])->name('api.v1.opportunities.participants.destroy');
    // Manual deal-total override
    Route::post('opportunities/{opportunity}/deal_price', [OpportunityController::class, 'setDealPrice'])->name('api.v1.opportunities.deal_price.set');
    Route::delete('opportunities/{opportunity}/deal_price', [OpportunityController::class, 'clearDealPrice'])->name('api.v1.opportunities.deal_price.clear');
    Route::post('opportunities/{opportunity}/recalculate', [OpportunityController::class, 'recalculatePricing'])->name('api.v1.opportunities.recalculate');
    Route::put('opportunities/{opportunity}/sections/{section}/deal_price', [OpportunityController::class, 'setSectionDealPrice'])->name('api.v1.opportunities.sections.deal_price.set');
    Route::delete('opportunities/{opportunity}/sections/{section}/deal_price', [OpportunityController::class, 'clearSectionDealPrice'])->name('api.v1.opportunities.sections.deal_price.clear');
    Route::post('opportunities/{opportunity}/apply_discount_category', [OpportunityController::class, 'applyDiscountCategory'])->name('api.v1.opportunities.apply_discount_category');
    Route::post('opportunities/{opportunity}/toggle_surcharge_group', [OpportunityController::class, 'toggleSurchargeGroup'])->name('api.v1.opportunities.toggle_surcharge_group');
    Route::post('opportunities/{opportunity}/items/{item}/override_surcharge', [OpportunityController::class, 'overrideItemSurcharge'])->name('api.v1.opportunities.override_item_surcharge');
    // Shortages (computed detection + non-PO resolution)
    Route::get('opportunities/{opportunity}/shortages', [ShortageController::class, 'index'])->name('api.v1.opportunities.shortages.index');
    // Persisted resolutions recorded against the opportunity (panel "active
    // resolutions" tab) — paginated + Ransack-filterable by status.
    Route::get('opportunities/{opportunity}/shortage_resolutions', [ShortageController::class, 'resolutions'])->name('api.v1.opportunities.shortage_resolutions.index');
    // Read-only confirmation-gate pre-check (Block/Warn/Allow) for the convert
    // dialog — NO acknowledgement recorded, NO auto-resolution.
    Route::get('opportunities/{opportunity}/shortage_gate', [ShortageController::class, 'gate'])->name('api.v1.opportunities.shortage_gate');
    Route::get('opportunities/{opportunity}/items/{item}/shortage_resolvers', [ShortageController::class, 'resolvers'])->name('api.v1.opportunities.shortage_resolvers');
    Route::post('opportunities/{opportunity}/shortages/acknowledge', [ShortageController::class, 'acknowledge'])->name('api.v1.opportunities.shortages.acknowledge');
    Route::post('shortage_resolutions', [ShortageController::class, 'resolve'])->name('api.v1.shortage_resolutions.store');
    Route::get('shortage_resolutions/batch', [ShortageController::class, 'batchResolvers'])->name('api.v1.shortage_resolutions.batch.index');
    Route::post('shortage_resolutions/batch', [ShortageController::class, 'batchResolve'])->name('api.v1.shortage_resolutions.batch.store');
    // Resolution status-transition lifecycle (§8.3): progress/cancel/fail a
    // recorded resolution. Each transition validates the §8.3 matrix (422 on an
    // illegal move) and emits the matching `shortage.resolution.*` event.
    Route::post('shortage_resolutions/{resolution}/transition', [ShortageController::class, 'transitionResolution'])->name('api.v1.shortage_resolutions.transition');
    Route::patch('shortage_resolutions/{resolution}/confirm', [ShortageController::class, 'confirmResolution'])->name('api.v1.shortage_resolutions.confirm');
    Route::patch('shortage_resolutions/{resolution}/start', [ShortageController::class, 'startResolution'])->name('api.v1.shortage_resolutions.start');
    Route::patch('shortage_resolutions/{resolution}/fulfill', [ShortageController::class, 'fulfillResolution'])->name('api.v1.shortage_resolutions.fulfill');
    Route::patch('shortage_resolutions/{resolution}/cancel', [ShortageController::class, 'cancelResolution'])->name('api.v1.shortage_resolutions.cancel');
    Route::patch('shortage_resolutions/{resolution}/fail', [ShortageController::class, 'failResolution'])->name('api.v1.shortage_resolutions.fail');

    // Quote versions (sub-resource — revisions + alternatives). Declared before the
    // opportunities apiResource so the explicit routes win. The diff route is
    // declared before `versions/{version}` so {version} does not swallow `diff`.
    Route::get('opportunities/{opportunity}/versions', [OpportunityVersionController::class, 'index'])->name('api.v1.opportunities.versions.index');
    Route::post('opportunities/{opportunity}/versions', [OpportunityVersionController::class, 'store'])->name('api.v1.opportunities.versions.store');
    Route::get('opportunities/{opportunity}/versions/{from}/diff/{to}', [OpportunityVersionController::class, 'diff'])->name('api.v1.opportunities.versions.diff');
    Route::post('opportunities/{opportunity}/versions/{version}/activate', [OpportunityVersionController::class, 'activate'])->name('api.v1.opportunities.versions.activate');
    Route::post('opportunities/{opportunity}/versions/{version}/send', [OpportunityVersionController::class, 'send'])->name('api.v1.opportunities.versions.send');
    Route::post('opportunities/{opportunity}/versions/{version}/accept', [OpportunityVersionController::class, 'accept'])->name('api.v1.opportunities.versions.accept');
    Route::post('opportunities/{opportunity}/versions/{version}/decline', [OpportunityVersionController::class, 'decline'])->name('api.v1.opportunities.versions.decline');
    Route::get('opportunities/{opportunity}/versions/{version}', [OpportunityVersionController::class, 'show'])->name('api.v1.opportunities.versions.show');
    Route::patch('opportunities/{opportunity}/versions/{version}', [OpportunityVersionController::class, 'update'])->name('api.v1.opportunities.versions.update');
    Route::delete('opportunities/{opportunity}/versions/{version}', [OpportunityVersionController::class, 'destroy'])->name('api.v1.opportunities.versions.destroy');

    Route::apiResource('opportunities', OpportunityController::class)->names('api.v1.opportunities');

    Route::post('opportunities/{opportunity}/invoices', [OpportunityInvoiceController::class, 'store'])->name('api.v1.opportunities.invoices.store');

    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('api.v1.invoices.pdf');
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('api.v1.invoices.issue');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('api.v1.invoices.void');
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('api.v1.invoices.payments.store');
    Route::post('invoices/{invoice}/refunds', [InvoiceController::class, 'recordRefund'])->name('api.v1.invoices.refunds.store');
    Route::post('invoices/{invoice}/credit_notes', [InvoiceController::class, 'issueCreditNote'])->name('api.v1.invoices.credit_notes.store');
    Route::post('invoices/{invoice}/email', [InvoiceController::class, 'email'])->name('api.v1.invoices.email');
    Route::apiResource('invoices', InvoiceController::class)->names('api.v1.invoices');

    Route::post('purchase_orders/{purchase_order}/send', [PurchaseOrderController::class, 'send'])->name('api.v1.purchase_orders.send');
    Route::post('purchase_orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('api.v1.purchase_orders.receive');
    Route::post('purchase_orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('api.v1.purchase_orders.cancel');
    Route::apiResource('purchase_orders', PurchaseOrderController::class)->names('api.v1.purchase_orders');

    Route::post('virtual_stock_intakes/{virtual_stock_intake}/confirm', [VirtualStockIntakeController::class, 'confirm'])->name('api.v1.virtual_stock_intakes.confirm');
    Route::post('virtual_stock_intakes/{virtual_stock_intake}/receive', [VirtualStockIntakeController::class, 'receive'])->name('api.v1.virtual_stock_intakes.receive');
    Route::post('virtual_stock_intakes/{virtual_stock_intake}/return', [VirtualStockIntakeController::class, 'return'])->name('api.v1.virtual_stock_intakes.return');
    Route::post('virtual_stock_intakes/{virtual_stock_intake}/cancel', [VirtualStockIntakeController::class, 'cancel'])->name('api.v1.virtual_stock_intakes.cancel');
    Route::post('virtual_stock_intakes/{virtual_stock_intake}/apportion', [VirtualStockIntakeController::class, 'apportion'])->name('api.v1.virtual_stock_intakes.apportion');
    Route::put('virtual_stock_intakes/{virtual_stock_intake}/cost_overrides', [VirtualStockIntakeController::class, 'setCostOverride'])->name('api.v1.virtual_stock_intakes.cost_overrides.set');
    Route::delete('virtual_stock_intakes/{virtual_stock_intake}/cost_overrides/{opportunity}', [VirtualStockIntakeController::class, 'clearCostOverride'])->name('api.v1.virtual_stock_intakes.cost_overrides.clear');
    Route::apiResource('virtual_stock_intakes', VirtualStockIntakeController::class)->names('api.v1.virtual_stock_intakes');

    // Rate Definitions
    Route::post('rate_definitions/{rate_definition}/duplicate', [RateDefinitionController::class, 'duplicate'])->name('api.v1.rate_definitions.duplicate');
    Route::apiResource('rate_definitions', RateDefinitionController::class)->names('api.v1.rate_definitions');

    // Rate Engine metadata (for external form builders)
    Route::get('rate_engine/strategies', [RateEngineMetaController::class, 'strategies'])->name('api.v1.rate_engine.strategies');
    Route::get('rate_engine/modifiers', [RateEngineMetaController::class, 'modifiers'])->name('api.v1.rate_engine.modifiers');
    Route::get('rate_engine/presets', [RateEngineMetaController::class, 'presets'])->name('api.v1.rate_engine.presets');
    Route::get('rate_engine/schema', [RateEngineMetaController::class, 'schema'])->name('api.v1.rate_engine.schema');
});
