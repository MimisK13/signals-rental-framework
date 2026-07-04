<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PurchaseOrders\CancelPurchaseOrder;
use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\DeleteDraftPurchaseOrder;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Actions\PurchaseOrders\UpdateDraftPurchaseOrder;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\PurchaseOrders\PurchaseOrderData;
use App\Data\PurchaseOrders\UpdateDraftPurchaseOrderData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\PurchaseOrder;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'number',
        'status',
        'po_type',
        'supplier_id',
        'store_id',
        'opportunity_id',
        'expected_at',
        'starts_at',
        'ends_at',
        'sent_at',
        'received_at',
        'cancelled_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'number',
        'status',
        'po_type',
        'total',
        'expected_at',
        'sent_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'supplier',
        'store',
        'opportunity',
        'items',
    ];

    protected function modelClass(): string
    {
        return PurchaseOrder::class;
    }

    protected function responseDataClass(): string
    {
        return PurchaseOrderData::class;
    }

    protected function createDataClass(): string
    {
        return CreatePurchaseOrderData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateDraftPurchaseOrderData::class;
    }

    protected function createActionClass(): string
    {
        return CreatePurchaseOrder::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateDraftPurchaseOrder::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteDraftPurchaseOrder::class;
    }

    protected function singularKey(): string
    {
        return 'purchase_order';
    }

    protected function pluralKey(): string
    {
        return 'purchase_orders';
    }

    protected function entityType(): string
    {
        return 'purchase_orders';
    }

    /**
     * @return array{view: string, create: string, edit: string, delete: string}
     */
    protected function permissions(): array
    {
        return [
            'view' => 'purchase_orders.view',
            'create' => 'purchase_orders.create',
            'edit' => 'purchase_orders.edit',
            'delete' => 'purchase_orders.delete',
        ];
    }

    /**
     * @return array{read: string, write: string}
     */
    protected function abilities(): array
    {
        return ['read' => 'purchase_orders:read', 'write' => 'purchase_orders:write'];
    }

    #[ApiResponse(200, 'Paginated purchase order collection', type: 'array{purchase_orders: list<array<string, mixed>>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PurchaseOrder::class);
        $this->authorizeApi($this->permissions()['view'], $this->abilities()['read']);

        return $this->resourceIndex($request);
    }

    #[ApiResponse(200, 'Purchase order resource', type: 'array{purchase_order: array<string, mixed>}')]
    public function show(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('view', $purchaseOrder);
        $this->authorizeApi($this->permissions()['view'], $this->abilities()['read']);

        return $this->resourceShow($request, $purchaseOrder);
    }

    #[ApiResponse(201, 'Created draft purchase order', type: 'array{purchase_order: array<string, mixed>}')]
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', PurchaseOrder::class);
        $this->authorizeApi($this->permissions()['create'], $this->abilities()['write']);

        $dto = CreatePurchaseOrderData::validateAndCreate($request->all());
        $result = (new CreatePurchaseOrder)($dto);

        return $this->respondWith(
            $result->toArray(),
            $this->singularKey(),
            Response::HTTP_CREATED,
        );
    }

    #[ApiResponse(200, 'Updated draft purchase order', type: 'array{purchase_order: array<string, mixed>}')]
    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('update', $purchaseOrder);
        $this->authorizeApi($this->permissions()['edit'], $this->abilities()['write']);

        $validated = $request->validate(UpdateDraftPurchaseOrderData::rules());
        $dto = UpdateDraftPurchaseOrderData::from($validated);
        $result = (new UpdateDraftPurchaseOrder)($purchaseOrder, $dto);

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    #[ApiResponse(204, 'Draft purchase order deleted')]
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('delete', $purchaseOrder);
        $this->authorizeApi($this->permissions()['delete'], $this->abilities()['write']);

        return $this->resourceDestroy($purchaseOrder);
    }

    #[ApiResponse(200, 'Sent purchase order', type: 'array{purchase_order: array<string, mixed>}')]
    public function send(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('send', $purchaseOrder);
        $this->authorizeApi('purchase_orders.send', $this->abilities()['write']);

        $supplierReference = $request->input('supplier_reference');
        $result = (new SendPurchaseOrder)(
            $purchaseOrder,
            is_string($supplierReference) ? $supplierReference : null,
        );

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    #[ApiResponse(200, 'Cancelled purchase order', type: 'array{purchase_order: array<string, mixed>}')]
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('cancel', $purchaseOrder);
        $this->authorizeApi('purchase_orders.cancel', $this->abilities()['write']);

        $reason = $request->input('reason');
        $result = (new CancelPurchaseOrder)(
            $purchaseOrder,
            is_string($reason) ? $reason : null,
        );

        return $this->respondWith($result->toArray(), $this->singularKey());
    }
}
