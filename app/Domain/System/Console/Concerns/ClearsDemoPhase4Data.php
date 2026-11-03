<?php

declare(strict_types=1);

namespace App\Domain\System\Console\Concerns;

use App\Domain\Availability\Models\Demand;
use App\Domain\Invoices\Models\CreditNote;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityCost;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Models\OpportunityVersion;
use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\SupplierBills\Models\SupplierBill;
use App\Domain\SupplierCosts\Models\ProductSupplierCost;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Models\ContainerItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbStateEvent;

trait ClearsDemoPhase4Data
{
    /**
     * Remove Phase-4 demo substrate: containers, quote-to-cash, sub-hire POs/VSIs,
     * supplier costs, and serialised assets. Event-sourced projections are deleted
     * directly and their verb event rows purged by state_id so replay cannot resurrect
     * demo aggregates (actions such as DeleteDraftInvoice cannot remove issued rows).
     */
    private function clearDemoPhase4Data(): void
    {
        $stateIds = collect();

        $stateIds = $stateIds->merge($this->clearDemoContainers());
        $stateIds = $stateIds->merge($this->clearDemoInvoices());
        $this->clearDemoSupplierBills();
        $stateIds = $stateIds->merge($this->clearDemoPurchaseOrdersAndVirtualStock());
        $stateIds = $stateIds->merge($this->clearDemoOpportunities());
        $this->clearDemoSupplierCosts();
        $this->clearDemoSerialisedAssets();

        $this->purgeVerbStateIds($stateIds->filter()->unique()->values()->all());
    }

    /**
     * @return Collection<int, int>
     */
    private function clearDemoContainers(): Collection
    {
        if (! Schema::hasTable('containers')) {
            return collect();
        }

        $demoStockLevelIds = StockLevel::query()
            ->where('asset_number', 'like', 'DEMO-%')
            ->pluck('id');

        if ($demoStockLevelIds->isEmpty()) {
            return collect();
        }

        $containerIds = Container::query()
            ->whereIn('serialised_item_id', $demoStockLevelIds)
            ->pluck('id');

        if ($containerIds->isEmpty()) {
            return collect();
        }

        if (Schema::hasTable('container_items')) {
            ContainerItem::query()->whereIn('container_id', $containerIds)->delete();
        }

        $removed = Container::query()->whereIn('id', $containerIds)->delete();
        $this->components->info("Removed {$removed} demo containers");

        return collect();
    }

    /**
     * @return Collection<int, int>
     */
    private function clearDemoInvoices(): Collection
    {
        if (! Schema::hasTable('invoices')) {
            return collect();
        }

        $invoiceQuery = Invoice::query()->where(function ($query): void {
            $query->whereJsonContains('tag_list', 'demo-data')
                ->orWhere('reference', 'like', 'DEMO-INV-%');
        });

        $stateIds = (clone $invoiceQuery)->pluck('state_id');
        $invoiceIds = (clone $invoiceQuery)->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return collect();
        }

        if (Schema::hasTable('credit_notes')) {
            CreditNote::query()->whereIn('invoice_id', $invoiceIds)->delete();
        }

        $removed = (clone $invoiceQuery)->forceDelete();
        $this->components->info("Removed {$removed} demo invoices");

        return $stateIds;
    }

    /**
     * @return Collection<int, int>
     */
    private function clearDemoPurchaseOrdersAndVirtualStock(): Collection
    {
        $stateIds = collect();

        if (Schema::hasTable('purchase_orders')) {
            $purchaseOrderQuery = PurchaseOrder::query()->where(function ($query): void {
                $query->where('reference', 'like', 'DEMO-PO-%')
                    ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->whereJsonContains('tag_list', 'demo-data'));
            });
            $purchaseOrderIds = (clone $purchaseOrderQuery)->pluck('id');
            $stateIds = $stateIds->merge((clone $purchaseOrderQuery)->pluck('state_id'));

            if ($purchaseOrderIds->isNotEmpty() && Schema::hasTable('virtual_stock_intakes')) {
                $vsiQuery = VirtualStockIntake::query()->whereIn('purchase_order_id', $purchaseOrderIds);
                $vsiIds = (clone $vsiQuery)->pluck('id');
                $stateIds = $stateIds->merge((clone $vsiQuery)->pluck('state_id'));

                if ($vsiIds->isNotEmpty()) {
                    Demand::query()
                        ->where('source_type', 'virtual_stock_intake')
                        ->whereIn('source_id', $vsiIds)
                        ->delete();

                    StockLevel::query()
                        ->whereIn('virtual_stock_intake_id', $vsiIds)
                        ->delete();

                    $vsiRemoved = (clone $vsiQuery)->forceDelete();
                    $this->components->info("Removed {$vsiRemoved} demo virtual stock intakes");
                }
            }

            if ($purchaseOrderIds->isNotEmpty()) {
                $poRemoved = (clone $purchaseOrderQuery)->forceDelete();
                $this->components->info("Removed {$poRemoved} demo purchase orders");
            }
        }

        return $stateIds;
    }

    /**
     * @return Collection<int, int>
     */
    private function clearDemoOpportunities(): Collection
    {
        if (! Schema::hasTable('opportunities')) {
            return collect();
        }

        $opportunityIds = Opportunity::query()
            ->whereJsonContains('tag_list', 'demo-data')
            ->pluck('id');

        if ($opportunityIds->isEmpty()) {
            return collect();
        }

        $itemIds = OpportunityItem::query()
            ->whereIn('opportunity_id', $opportunityIds)
            ->pluck('id');

        if ($itemIds->isNotEmpty() && Schema::hasTable('demands')) {
            Demand::query()
                ->where('source_type', 'opportunity_item')
                ->whereIn('source_id', $itemIds)
                ->delete();
        }

        $stateIds = Opportunity::query()->whereIn('id', $opportunityIds)->pluck('state_id')
            ->merge(OpportunityItem::query()->whereIn('opportunity_id', $opportunityIds)->pluck('state_id'))
            ->merge(OpportunityCost::query()->whereIn('opportunity_id', $opportunityIds)->pluck('state_id'))
            ->merge(OpportunityVersion::query()->whereIn('opportunity_id', $opportunityIds)->pluck('state_id'));

        if ($itemIds->isNotEmpty()) {
            $stateIds = $stateIds->merge(
                OpportunityItemAsset::query()->whereIn('opportunity_item_id', $itemIds)->pluck('state_id'),
            );
        }

        $removed = Opportunity::query()->whereIn('id', $opportunityIds)->forceDelete();
        $this->components->info("Removed {$removed} demo opportunities");

        return $stateIds;
    }

    private function clearDemoSupplierCosts(): void
    {
        if (! Schema::hasTable('product_supplier_costs')) {
            return;
        }

        $demoProductIds = Product::query()
            ->whereJsonContains('tag_list', 'demo-data')
            ->pluck('id');

        if ($demoProductIds->isEmpty()) {
            return;
        }

        $removed = ProductSupplierCost::query()
            ->whereIn('product_id', $demoProductIds)
            ->delete();

        if ($removed > 0) {
            $this->components->info("Removed {$removed} demo supplier costs");
        }
    }

    private function clearDemoSupplierBills(): void
    {
        if (! Schema::hasTable('supplier_bills')) {
            return;
        }

        $removed = SupplierBill::query()
            ->withTrashed()
            ->where(function ($query): void {
                $query->where('document_number', 'like', 'DEMO-SB-%')
                    ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->whereJsonContains('tag_list', 'demo-data'));
            })
            ->forceDelete();

        if ($removed > 0) {
            $this->components->info("Removed {$removed} demo supplier bills");
        }
    }

    private function clearDemoSerialisedAssets(): void
    {
        if (! Schema::hasTable('stock_levels')) {
            return;
        }

        $removed = StockLevel::query()
            ->where('asset_number', 'like', 'DEMO-%')
            ->delete();

        if ($removed > 0) {
            $this->components->info("Removed {$removed} demo serialised assets");
        }
    }

    /**
     * @param  list<int>  $stateIds
     */
    private function purgeVerbStateIds(array $stateIds): void
    {
        if ($stateIds === [] || ! Schema::hasTable('verb_state_events')) {
            return;
        }

        $eventIds = VerbStateEvent::query()
            ->whereIn('state_id', $stateIds)
            ->distinct()
            ->pluck('event_id');

        if ($eventIds->isNotEmpty() && Schema::hasTable('verb_events')) {
            VerbEvent::query()->whereIn('id', $eventIds)->delete();
        }

        VerbStateEvent::query()->whereIn('state_id', $stateIds)->delete();

        if (Schema::hasTable('verb_snapshots')) {
            DB::table('verb_snapshots')->whereIn('state_id', $stateIds)->delete();
        }
    }
}
