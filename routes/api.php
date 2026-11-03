<?php

use App\Http\Controllers\Api\V1\ActionLogController;
use App\Http\Controllers\Api\V1\BundleRuleController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\DiscountCategoryController;
use App\Http\Controllers\Api\V1\DiscountRuleController;
use App\Http\Controllers\Api\V1\EndpointCatalogueController;
use App\Http\Controllers\Api\V1\MemberAddressController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MemberEmailController;
use App\Http\Controllers\Api\V1\MemberLinkController;
use App\Http\Controllers\Api\V1\MemberNotificationPreferenceController;
use App\Http\Controllers\Api\V1\MemberPhoneController;
use App\Http\Controllers\Api\V1\MemberRelationshipController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\NotificationSettingController;
use App\Http\Controllers\Api\V1\PriceCategoryController;
use App\Http\Controllers\Api\V1\RequestLogController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SchemaController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SurchargeController;
use App\Http\Controllers\Api\V1\SurchargeGroupController;
use App\Http\Controllers\Api\V1\SystemController;
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

    Route::get('notification_preferences', [NotificationPreferenceController::class, 'index'])->name('api.v1.notification_preferences.index');
    Route::put('notification_preferences', [NotificationPreferenceController::class, 'update'])->name('api.v1.notification_preferences.update');
    Route::get('members/{member}/notification_preferences', [MemberNotificationPreferenceController::class, 'index'])->name('api.v1.members.notification_preferences.index');
    Route::put('members/{member}/notification_preferences', [MemberNotificationPreferenceController::class, 'update'])->name('api.v1.members.notification_preferences.update');

    Route::get('notification_settings', [NotificationSettingController::class, 'index'])->name('api.v1.notification_settings.index');
    Route::put('notification_settings', [NotificationSettingController::class, 'update'])->name('api.v1.notification_settings.update');

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

    // Custom Views
    // Countries (read-only)
    Route::apiResource('countries', CountryController::class)->only(['index', 'show'])->names('api.v1.countries');

    // Custom Fields

    // Pricing Categories
    Route::apiResource('discount_categories', DiscountCategoryController::class)->names('api.v1.discount_categories');
    Route::apiResource('discount_rules', DiscountRuleController::class)->names('api.v1.discount_rules');
    Route::apiResource('bundle_rules', BundleRuleController::class)->names('api.v1.bundle_rules');
    Route::apiResource('surcharge_groups', SurchargeGroupController::class)->names('api.v1.surcharge_groups');
    Route::apiResource('surcharges', SurchargeController::class)->names('api.v1.surcharges');
    Route::apiResource('price_categories', PriceCategoryController::class)->names('api.v1.price_categories');

    // Stock Levels

    Route::post('virtual_stock_intakes/{virtual_stock_intake}/confirm', [VirtualStockIntakeController::class, 'confirm'])->name('api.v1.virtual_stock_intakes.confirm');
    Route::post('virtual_stock_intakes/{virtual_stock_intake}/receive', [VirtualStockIntakeController::class, 'receive'])->name('api.v1.virtual_stock_intakes.receive');
    Route::post('virtual_stock_intakes/{virtual_stock_intake}/return', [VirtualStockIntakeController::class, 'return'])->name('api.v1.virtual_stock_intakes.return');
    Route::post('virtual_stock_intakes/{virtual_stock_intake}/cancel', [VirtualStockIntakeController::class, 'cancel'])->name('api.v1.virtual_stock_intakes.cancel');
    Route::post('virtual_stock_intakes/{virtual_stock_intake}/apportion', [VirtualStockIntakeController::class, 'apportion'])->name('api.v1.virtual_stock_intakes.apportion');
    Route::put('virtual_stock_intakes/{virtual_stock_intake}/cost_overrides', [VirtualStockIntakeController::class, 'setCostOverride'])->name('api.v1.virtual_stock_intakes.cost_overrides.set');
    Route::delete('virtual_stock_intakes/{virtual_stock_intake}/cost_overrides/{opportunity}', [VirtualStockIntakeController::class, 'clearCostOverride'])->name('api.v1.virtual_stock_intakes.cost_overrides.clear');
    Route::apiResource('virtual_stock_intakes', VirtualStockIntakeController::class)->names('api.v1.virtual_stock_intakes');

});
