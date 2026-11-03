<?php

declare(strict_types=1);

use App\Domain\API\Presentation\Http\Middleware\ForceJsonResponse;
use App\Domain\API\Presentation\Http\Middleware\LogApiRequest;
use App\Domain\Projects\Opportunities\Presentation\Http\Controllers\Api\V1\OpportunityController;
use App\Domain\Projects\Opportunities\Presentation\Http\Controllers\Api\V1\OpportunityVersionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', LogApiRequest::class, 'signals.active-user'])
    ->group(function (): void {
        // Opportunities (event-sourced lifecycle)
        // Read sub-resources (assets / availability / activity) — declared before the
        // apiResource so the explicit GET routes win.
        Route::get('opportunities/{opportunity}/assets', [OpportunityController::class, 'assets'])->name('api.v1.opportunities.assets');
        Route::get('opportunities/{opportunity}/availability', [OpportunityController::class, 'availability'])->name('api.v1.opportunities.availability');
        Route::get('opportunities/{opportunity}/planning', [OpportunityController::class, 'planning'])->name('api.v1.opportunities.planning');
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
    });
