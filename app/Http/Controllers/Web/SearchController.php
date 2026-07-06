<?php

namespace App\Http\Controllers\Web;

use App\Enums\MembershipType;
use App\Enums\OpportunityState;
use App\Enums\ProductType;
use App\Models\Activity;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\VirtualStockIntake;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->value();

        if (mb_strlen($query) < 2) {
            return response()->json($this->emptyResults());
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        $results = $this->emptyResults();

        // Search members
        if (Gate::allows('members.view')) {
            $members = Member::query()
                ->where(function ($builder) use ($escaped): void {
                    $builder->where('name', 'ilike', '%'.$escaped.'%')
                        ->orWhere('number', 'ilike', '%'.$escaped.'%');
                })
                ->orderBy('name')
                ->limit(8)
                ->get();

            foreach ($members as $member) {
                /** @var MembershipType $type */
                $type = $member->membership_type;
                $words = preg_split('/\s+/', trim($member->name));
                $initials = mb_strtoupper(
                    mb_substr($words[0] ?? '', 0, 1).mb_substr($words[1] ?? '', 0, 1)
                );
                $results['members'][] = [
                    'id' => $member->id,
                    'name' => $member->name,
                    'type' => $type->label(),
                    'typeValue' => $type->value,
                    'isActive' => $member->is_active,
                    'initials' => $initials,
                    'icon' => $this->iconUrl($member->icon_thumb_url),
                    'url' => route('members.show', $member->id),
                ];
            }
        }

        // Search products
        if (Gate::allows('products.view')) {
            $products = Product::query()
                ->where('name', 'ilike', '%'.$escaped.'%')
                ->orderBy('name')
                ->limit(5)
                ->get();

            foreach ($products as $product) {
                /** @var ProductType $productType */
                $productType = $product->product_type;
                $results['products'][] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'type' => $productType->label(),
                    'typeValue' => $productType->value,
                    'icon' => $this->iconUrl($product->icon_thumb_url),
                    'url' => route('products.show', $product->id),
                ];
            }
        }

        // Search stock levels
        if (Gate::allows('stock.view')) {
            $stockLevels = StockLevel::query()
                ->where('asset_number', 'ilike', '%'.$escaped.'%')
                ->orderBy('asset_number')
                ->limit(5)
                ->get();

            foreach ($stockLevels as $stockLevel) {
                $results['stock_levels'][] = [
                    'id' => $stockLevel->id,
                    'name' => $stockLevel->item_name ?? ('Asset #'.$stockLevel->asset_number),
                    'type' => 'Stock Level',
                    'typeValue' => 'stock_level',
                    'url' => route('stock-levels.show', $stockLevel->id),
                ];
            }
        }

        // Search product groups
        if (Gate::allows('products.view')) {
            $productGroups = ProductGroup::query()
                ->where('name', 'ilike', '%'.$escaped.'%')
                ->orderBy('name')
                ->limit(5)
                ->get();

            foreach ($productGroups as $group) {
                $results['product_groups'][] = [
                    'id' => $group->id,
                    'name' => $group->name,
                    'type' => 'Product Group',
                    'typeValue' => 'product_group',
                    'url' => route('products.index', ['group' => $group->id]),
                ];
            }
        }

        // Search activities
        if (Gate::allows('activities.view')) {
            $activities = Activity::query()
                ->where('subject', 'ilike', '%'.$escaped.'%')
                ->with(['owner', 'type'])
                ->orderBy('subject')
                ->limit(5)
                ->get();

            foreach ($activities as $activity) {
                $type = $activity->relationLoaded('type') ? $activity->type : null;
                $results['activities'][] = [
                    'id' => $activity->id,
                    'name' => $activity->subject,
                    'type' => $type !== null ? $type->name : 'Activity',
                    'typeValue' => 'activity',
                    'url' => route('activities.show', $activity->id),
                ];
            }
        }

        // Search opportunities (quotes + orders) by subject, RMS number, or
        // customer reference. Soft-deleted opportunities are excluded by the
        // model's default SoftDeletes scope.
        if (Gate::allows('opportunities.view')) {
            $opportunities = Opportunity::query()
                ->where(function ($builder) use ($escaped): void {
                    $builder->where('subject', 'ilike', '%'.$escaped.'%')
                        ->orWhere('number', 'ilike', '%'.$escaped.'%')
                        ->orWhere('reference', 'ilike', '%'.$escaped.'%');
                })
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($opportunities as $opportunity) {
                /** @var OpportunityState $state */
                $state = $opportunity->state;
                $results['opportunities'][] = [
                    'id' => $opportunity->id,
                    'name' => $opportunity->subject,
                    'number' => $opportunity->number,
                    'type' => $state->label(),
                    'typeValue' => 'opportunity',
                    'url' => route('opportunities.show', $opportunity->id),
                ];
            }
        }

        // Search invoices by number or customer reference.
        if (Gate::allows('invoices.view')) {
            $invoices = Invoice::query()
                ->where(function ($builder) use ($escaped): void {
                    $builder->where('number', 'ilike', '%'.$escaped.'%')
                        ->orWhere('reference', 'ilike', '%'.$escaped.'%');
                })
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($invoices as $invoice) {
                $statusLabel = (string) (config('invoices.statuses.'.$invoice->status.'.label') ?? $invoice->status);
                $results['invoices'][] = [
                    'id' => $invoice->id,
                    'name' => $invoice->number,
                    'number' => $statusLabel,
                    'typeValue' => 'invoice',
                    'url' => route('invoices.show', $invoice->id),
                ];
            }
        }

        // Search credit notes by number or invoice member name.
        if (Gate::allows('invoices.view')) {
            $creditNotes = CreditNote::query()
                ->where(function ($builder) use ($escaped): void {
                    $builder->where('number', 'ilike', '%'.$escaped.'%')
                        ->orWhereHas('invoice.member', fn ($memberQuery) => $memberQuery->where('name', 'ilike', '%'.$escaped.'%'));
                })
                ->with(['invoice.member'])
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($creditNotes as $creditNote) {
                $statusLabel = (string) (config('credit_notes.statuses.'.$creditNote->status.'.label') ?? $creditNote->status);
                $memberName = '';
                if ($creditNote->relationLoaded('invoice') && $creditNote->invoice !== null) {
                    $invoice = $creditNote->invoice;
                    if ($invoice->relationLoaded('member') && $invoice->member !== null) {
                        $memberName = $invoice->member->name;
                    }
                }
                $results['credit_notes'][] = [
                    'id' => $creditNote->id,
                    'name' => $creditNote->number,
                    'number' => $memberName,
                    'type' => $statusLabel,
                    'typeValue' => 'credit_note',
                    'url' => route('invoices.show', $creditNote->invoice_id),
                ];
            }
        }

        // Search purchase orders by number or supplier name.
        if (Gate::allows('purchase_orders.view')) {
            $purchaseOrders = PurchaseOrder::query()
                ->where(function ($builder) use ($escaped): void {
                    $builder->where('number', 'ilike', '%'.$escaped.'%')
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'ilike', '%'.$escaped.'%'));
                })
                ->with(['supplier'])
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($purchaseOrders as $purchaseOrder) {
                $statusLabel = (string) (config('purchase_orders.statuses.'.$purchaseOrder->status.'.label') ?? $purchaseOrder->status);
                $supplierName = $purchaseOrder->relationLoaded('supplier') && $purchaseOrder->supplier !== null
                    ? $purchaseOrder->supplier->name
                    : '';
                $results['purchase_orders'][] = [
                    'id' => $purchaseOrder->id,
                    'name' => $purchaseOrder->number,
                    'number' => $supplierName,
                    'type' => $statusLabel,
                    'typeValue' => 'purchase_order',
                    'url' => route('purchase-orders.show', $purchaseOrder->id),
                ];
            }
        }

        // Search virtual stock intakes by number.
        if (Gate::allows('virtual_stock.view')) {
            $virtualStockIntakes = VirtualStockIntake::query()
                ->where('number', 'ilike', '%'.$escaped.'%')
                ->with(['product'])
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($virtualStockIntakes as $intake) {
                $statusLabel = (string) (config('virtual_stock.statuses.'.$intake->status.'.label') ?? $intake->status);
                $productName = $intake->relationLoaded('product') && $intake->product !== null
                    ? $intake->product->name
                    : '';
                $results['virtual_stock_intakes'][] = [
                    'id' => $intake->id,
                    'name' => $intake->number,
                    'number' => $productName,
                    'type' => $statusLabel,
                    'typeValue' => 'virtual_stock_intake',
                    'url' => route('virtual-stock.show', $intake->id),
                ];
            }
        }

        // Search invoice payments by number or reference.
        if (Gate::allows('invoices.view')) {
            $invoicePayments = InvoicePayment::query()
                ->forAccessibleStores()
                ->where(function ($builder) use ($escaped): void {
                    $builder->where('number', 'ilike', '%'.$escaped.'%')
                        ->orWhere('reference', 'ilike', '%'.$escaped.'%');
                })
                ->with(['invoice'])
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($invoicePayments as $payment) {
                $displayName = $payment->number ?? $payment->reference ?? ('Payment #'.$payment->id);
                $hint = $payment->reference ?? '';
                if ($hint === '' && $payment->relationLoaded('invoice') && $payment->invoice !== null) {
                    $hint = $payment->invoice->number;
                }
                $typeLabel = $payment->isRefund() ? 'Refund' : 'Payment';
                $results['invoice_payments'][] = [
                    'id' => $payment->id,
                    'name' => $displayName,
                    'number' => $hint,
                    'type' => $typeLabel,
                    'typeValue' => 'invoice_payment',
                    'url' => route('invoices.show', $payment->invoice_id),
                ];
            }
        }

        return response()->json($results);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function emptyResults(): array
    {
        return [
            'members' => [],
            'products' => [],
            'stock_levels' => [],
            'product_groups' => [],
            'activities' => [],
            'opportunities' => [],
            'invoices' => [],
            'credit_notes' => [],
            'purchase_orders' => [],
            'virtual_stock_intakes' => [],
            'invoice_payments' => [],
        ];
    }

    /**
     * Resolve a signed URL for a stored icon thumbnail, or null when absent
     * or unreadable (so the palette falls back to its type glyph).
     */
    private function iconUrl(?string $path): ?string
    {
        return app(FileService::class)->signedUrlOrNull($path);
    }
}
