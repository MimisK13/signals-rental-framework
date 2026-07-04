<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\VirtualStock\ApportionVirtualStockCost;
use App\Actions\VirtualStock\CancelVirtualStockIntake;
use App\Actions\VirtualStock\ClearVirtualStockCostOverride;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\CreateVirtualStockIntake;
use App\Actions\VirtualStock\DeleteDraftVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Actions\VirtualStock\ReturnVirtualStockIntake;
use App\Actions\VirtualStock\SetVirtualStockCostOverride;
use App\Actions\VirtualStock\UpdateDraftVirtualStockIntake;
use App\Data\VirtualStock\CreateVirtualStockIntakeData;
use App\Data\VirtualStock\ReturnVirtualStockIntakeData;
use App\Data\VirtualStock\SetVirtualStockCostOverrideData;
use App\Data\VirtualStock\UpdateDraftVirtualStockIntakeData;
use App\Data\VirtualStock\VirtualStockIntakeData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\Opportunity;
use App\Models\VirtualStockIntake;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class VirtualStockIntakeController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'number',
        'status',
        'product_id',
        'store_id',
        'purchase_order_id',
        'primary_opportunity_id',
        'available_from',
        'available_until',
        'confirmed_at',
        'received_at',
        'returned_at',
        'cancelled_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'number',
        'status',
        'quantity',
        'available_from',
        'available_until',
        'confirmed_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'product',
        'store',
        'purchaseOrder',
        'purchaseOrderItem',
        'virtualAssets',
    ];

    protected function modelClass(): string
    {
        return VirtualStockIntake::class;
    }

    protected function responseDataClass(): string
    {
        return VirtualStockIntakeData::class;
    }

    protected function createDataClass(): string
    {
        return CreateVirtualStockIntakeData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateDraftVirtualStockIntakeData::class;
    }

    protected function createActionClass(): string
    {
        return CreateVirtualStockIntake::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateDraftVirtualStockIntake::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteDraftVirtualStockIntake::class;
    }

    protected function singularKey(): string
    {
        return 'virtual_stock_intake';
    }

    protected function pluralKey(): string
    {
        return 'virtual_stock_intakes';
    }

    protected function entityType(): string
    {
        return 'virtual_stock_intakes';
    }

    /**
     * @return array{view: string, create: string, edit: string, delete: string}
     */
    protected function permissions(): array
    {
        return [
            'view' => 'virtual_stock.view',
            'create' => 'virtual_stock.create',
            'edit' => 'virtual_stock.edit',
            'delete' => 'virtual_stock.delete',
        ];
    }

    /**
     * @return array{read: string, write: string}
     */
    protected function abilities(): array
    {
        return ['read' => 'virtual_stock:read', 'write' => 'virtual_stock:write'];
    }

    #[ApiResponse(200, 'Paginated virtual stock intake collection', type: 'array{virtual_stock_intakes: list<array<string, mixed>>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', VirtualStockIntake::class);
        $this->authorizeApi($this->permissions()['view'], $this->abilities()['read']);

        $lateReturn = $this->extractLateReturnFilter($request);

        $query = VirtualStockIntake::query()->withCount('resolutionItems');
        $query = $this->applyIncludes($query, $request);
        ['query' => $query, 'view' => $view] = $this->applyViewOrFilters($query, $request, $this->entityType());

        if ($lateReturn !== null) {
            $query->lateReturn($lateReturn);
        }

        $paginator = $this->paginateQuery($query, $request);

        $dataClass = $this->responseDataClass();
        $items = $paginator->getCollection()->map(
            fn (VirtualStockIntake $model): array => $dataClass::fromModel($model)->toArray()
        )->all();

        $meta = [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'page' => $paginator->currentPage(),
        ];

        if ($view !== null) {
            $meta['view'] = [
                'id' => $view->id,
                'name' => $view->name,
            ];
        }

        return response()->json([
            $this->pluralKey() => $items,
            'meta' => $meta,
        ]);
    }

    #[ApiResponse(200, 'Virtual stock intake resource', type: 'array{virtual_stock_intake: array<string, mixed>}')]
    public function show(Request $request, VirtualStockIntake $virtualStockIntake): JsonResponse
    {
        Gate::authorize('view', $virtualStockIntake);
        $this->authorizeApi($this->permissions()['view'], $this->abilities()['read']);

        return $this->resourceShow($request, $virtualStockIntake);
    }

    #[ApiResponse(201, 'Created draft virtual stock intake', type: 'array{virtual_stock_intake: array<string, mixed>}')]
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', VirtualStockIntake::class);
        $this->authorizeApi($this->permissions()['create'], $this->abilities()['write']);

        $dto = CreateVirtualStockIntakeData::validateAndCreate($request->all());
        $result = (new CreateVirtualStockIntake)($dto);

        return $this->respondWith(
            $result->toArray(),
            $this->singularKey(),
            Response::HTTP_CREATED,
        );
    }

    #[ApiResponse(200, 'Updated draft virtual stock intake', type: 'array{virtual_stock_intake: array<string, mixed>}')]
    public function update(Request $request, VirtualStockIntake $virtualStockIntake): JsonResponse
    {
        Gate::authorize('update', $virtualStockIntake);
        $this->authorizeApi($this->permissions()['edit'], $this->abilities()['write']);

        $validated = UpdateDraftVirtualStockIntakeData::validateAndCreate($request->all());
        $result = (new UpdateDraftVirtualStockIntake)($virtualStockIntake, $validated);

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    #[ApiResponse(204, 'Draft virtual stock intake deleted')]
    public function destroy(VirtualStockIntake $virtualStockIntake): JsonResponse
    {
        Gate::authorize('delete', $virtualStockIntake);
        $this->authorizeApi($this->permissions()['delete'], $this->abilities()['write']);

        return $this->resourceDestroy($virtualStockIntake);
    }

    #[ApiResponse(200, 'Confirmed virtual stock intake', type: 'array{virtual_stock_intake: array<string, mixed>}')]
    public function confirm(VirtualStockIntake $virtualStockIntake): JsonResponse
    {
        Gate::authorize('confirm', $virtualStockIntake);
        $this->authorizeApi('virtual_stock.confirm', $this->abilities()['write']);

        $result = app(ConfirmVirtualStockIntake::class)($virtualStockIntake);

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    #[ApiResponse(200, 'Received virtual stock intake', type: 'array{virtual_stock_intake: array<string, mixed>}')]
    public function receive(VirtualStockIntake $virtualStockIntake): JsonResponse
    {
        Gate::authorize('receive', $virtualStockIntake);
        $this->authorizeApi('virtual_stock.receive', $this->abilities()['write']);

        $result = app(ReceiveVirtualStockIntake::class)($virtualStockIntake);

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    #[ApiResponse(200, 'Returned virtual stock intake', type: 'array{virtual_stock_intake: array<string, mixed>}')]
    public function return(Request $request, VirtualStockIntake $virtualStockIntake): JsonResponse
    {
        Gate::authorize('return', $virtualStockIntake);
        $this->authorizeApi('virtual_stock.return', $this->abilities()['write']);

        $dto = ReturnVirtualStockIntakeData::validateAndCreate($request->all());
        $result = app(ReturnVirtualStockIntake::class)($virtualStockIntake, $dto);

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    #[ApiResponse(200, 'Cancelled virtual stock intake', type: 'array{virtual_stock_intake: array<string, mixed>}')]
    public function cancel(Request $request, VirtualStockIntake $virtualStockIntake): JsonResponse
    {
        Gate::authorize('cancel', $virtualStockIntake);
        $this->authorizeApi('virtual_stock.cancel', $this->abilities()['write']);

        $reason = $request->input('reason');
        $result = app(CancelVirtualStockIntake::class)(
            $virtualStockIntake,
            is_string($reason) ? $reason : null,
        );

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    #[ApiResponse(200, 'Apportioned virtual stock costs', type: 'array{apportionment: array<string, mixed>}')]
    public function apportion(VirtualStockIntake $virtualStockIntake, Request $request): JsonResponse
    {
        Gate::authorize('view', $virtualStockIntake);
        $this->authorizeApi($this->permissions()['view'], $this->abilities()['read']);

        $strategy = $request->input('strategy');
        $result = app(ApportionVirtualStockCost::class)(
            $virtualStockIntake,
            is_string($strategy) ? $strategy : null,
        );

        return $this->respondWith($result->toArray(), 'apportionment');
    }

    #[ApiResponse(200, 'Cost override set and apportionment recalculated', type: 'array{apportionment: array<string, mixed>}')]
    public function setCostOverride(Request $request, VirtualStockIntake $virtualStockIntake): JsonResponse
    {
        Gate::authorize('manageCostOverrides', $virtualStockIntake);
        $this->authorizeApi($this->permissions()['edit'], $this->abilities()['write']);
        Gate::authorize('costs.view');

        $dto = SetVirtualStockCostOverrideData::validateAndCreate($request->all());
        $result = app(SetVirtualStockCostOverride::class)($virtualStockIntake, $dto);

        return $this->respondWith($result->toArray(), 'apportionment');
    }

    #[ApiResponse(200, 'Cost override cleared and apportionment recalculated', type: 'array{apportionment: array<string, mixed>}')]
    public function clearCostOverride(VirtualStockIntake $virtualStockIntake, Opportunity $opportunity): JsonResponse
    {
        Gate::authorize('manageCostOverrides', $virtualStockIntake);
        $this->authorizeApi($this->permissions()['edit'], $this->abilities()['write']);
        Gate::authorize('costs.view');

        $result = app(ClearVirtualStockCostOverride::class)($virtualStockIntake, $opportunity);

        return $this->respondWith($result->toArray(), 'apportionment');
    }

    /**
     * Extract `is_late_return_{predicate}` from the Ransack `q` bag before the
     * generic filter whitelist runs — late return is derived, not a column.
     */
    protected function extractLateReturnFilter(Request $request): ?bool
    {
        $filters = $request->input('q', []);

        if (! is_array($filters)) {
            return null;
        }

        if (array_key_exists('is_late_return_true', $filters)) {
            $request->merge(['q' => collect($filters)->except('is_late_return_true')->all()]);

            return true;
        }

        if (array_key_exists('is_late_return_false', $filters)) {
            $request->merge(['q' => collect($filters)->except('is_late_return_false')->all()]);

            return false;
        }

        if (array_key_exists('is_late_return_eq', $filters)) {
            $request->merge(['q' => collect($filters)->except('is_late_return_eq')->all()]);

            return filter_var($filters['is_late_return_eq'], FILTER_VALIDATE_BOOLEAN);
        }

        return null;
    }
}
