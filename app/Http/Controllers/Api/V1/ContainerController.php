<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Containers\CreateContainer;
use App\Actions\Containers\CreateTemporaryContainer;
use App\Actions\Containers\DissolveContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\SealContainer;
use App\Actions\Containers\TransferContainerItem;
use App\Actions\Containers\UnpackContainerItem;
use App\Actions\Containers\UnsealContainer;
use App\Actions\Containers\UpdateContainer;
use App\Data\Containers\ContainerData;
use App\Data\Containers\CreateContainerData;
use App\Data\Containers\CreateTemporaryContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\TransferContainerItemData;
use App\Data\Containers\UnpackContainerItemData;
use App\Data\Containers\UnsealContainerData;
use App\Data\Containers\UpdateContainerData;
use App\Exceptions\Containers\ContainerPackConflictException;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\Container;
use App\Services\Containers\ContainerManifestService;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API surface for containers (serialised-containers.md). Read, create,
 * update, pack/unpack, seal, and unseal are exposed here — dissolve, dispatch,
 * return, and repack stay in later Phase-4 slices.
 *
 * Pack/unpack delegate to the existing {@see PackContainerItem} /
 * {@see UnpackContainerItem} actions, which create/release the container demand
 * inside a single transaction. The already-booked-asset guard surfaces as a 422.
 */
class ContainerController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'barcode',
        'status',
        'scan_mode',
        'is_temporary',
        'product_id',
        'store_id',
        'opportunity_id',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'barcode',
        'status',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'product',
        'store',
        'activeItems',
        'containerItems',
    ];

    /**
     * List containers with filtering, sorting, and pagination.
     */
    #[ApiResponse(200, 'Container list', type: 'array{containers: list<array{id: int, uuid: string, name: string, barcode: string|null, status: string, scan_mode: string, is_temporary: bool, product_id: int|null, store_id: int|null, opportunity_id: int|null, availability_mode: string, created_at: string, updated_at: string}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('containers.view', 'containers:read');

        $query = Container::query();
        $query = $this->applyIncludes($query, $request);
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        $paginator = $this->paginateQuery($query, $request);

        $containers = $paginator->getCollection()->map(
            fn (Container $container): array => ContainerData::fromModel($container)->toArray()
        )->all();

        return response()->json([
            'containers' => $containers,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }

    /**
     * Show a single container with its currently-packed items (`?include=items`).
     */
    #[ApiResponse(200, 'Container details', type: 'array{container: array{id: int, uuid: string, name: string, status: string, availability_mode: string}}')]
    public function show(Request $request, Container $container): JsonResponse
    {
        $this->authorizeApi('containers.view', 'containers:read');

        $this->applyIncludes(Container::query(), $request, $container);

        // show always returns the currently-packed contents.
        $container->loadMissing('activeItems');

        return $this->respondWith(
            ContainerData::fromModel($container)->toArray(),
            'container',
        );
    }

    /**
     * Create a product-backed container from a containerable serialised item.
     */
    #[ApiResponse(201, 'Container created')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('containers.create', 'containers:write');

        $data = CreateContainerData::from($request->validate(CreateContainerData::rules()));

        $container = (new CreateContainer)($data);

        return $this->respondWith($container->toArray(), 'container', Response::HTTP_CREATED);
    }

    /**
     * Create a barcode-only temporary container.
     */
    #[ApiResponse(201, 'Temporary container created')]
    public function storeTemporary(Request $request): JsonResponse
    {
        $this->authorizeApi('containers.create_temporary', 'containers:write');

        $data = CreateTemporaryContainerData::from($request->validate(CreateTemporaryContainerData::rules()));

        $container = (new CreateTemporaryContainer)($data);

        return $this->respondWith($container->toArray(), 'container', Response::HTTP_CREATED);
    }

    /**
     * Update an open container's name, barcode, or notes.
     */
    #[ApiResponse(200, 'Container updated')]
    public function update(Request $request, Container $container): JsonResponse
    {
        $this->authorizeApi('containers.create', 'containers:write');

        $data = UpdateContainerData::from($request->validate(UpdateContainerData::rules()));

        $result = (new UpdateContainer)($container, $data);

        return $this->respondWith($result->toArray(), 'container');
    }

    /**
     * Pack a serialised item into an open container.
     *
     * Creates the membership row and (for kit / hybrid-fixed containers) the
     * container demand that holds the item from individual availability. Packing
     * a non-serialised item, an item at a different store, or nesting depth
     * exceeded yields a 422. Conflicts (already in container, checked out, nesting
     * confirmation) yield 409 with resolution options.
     */
    #[ApiResponse(201, 'Item packed')]
    #[ApiResponse(409, 'Pack conflict requiring resolution')]
    public function pack(Request $request, Container $container): JsonResponse
    {
        $this->authorizeApi('containers.pack', 'containers:write');

        $data = PackContainerItemData::from($request->validate(PackContainerItemData::rules()));

        try {
            $result = (new PackContainerItem)($container, $data);
        } catch (ContainerPackConflictException $e) {
            return response()->json($e->payload(), Response::HTTP_CONFLICT);
        }

        return response()->json([
            'container_item' => $result->container_item->toArray(),
            'notices' => $result->notices,
        ], Response::HTTP_CREATED);
    }

    /**
     * Unpack a serialised item from an open container.
     *
     * Soft-closes the active membership and releases its container demand so the
     * item returns to individual availability. Unpacking an item that is not
     * packed in this container yields a 422.
     */
    #[ApiResponse(200, 'Item unpacked')]
    public function unpack(Request $request, Container $container): JsonResponse
    {
        $this->authorizeApi('containers.pack', 'containers:write');

        $data = UnpackContainerItemData::from($request->validate(UnpackContainerItemData::rules()));

        $item = (new UnpackContainerItem)($container, $data);

        return $this->respondWith($item->toArray(), 'container_item');
    }

    /**
     * Seal an open container (QA gate — strict mode validates template match).
     */
    #[ApiResponse(200, 'Container sealed')]
    public function seal(Container $container): JsonResponse
    {
        $this->authorizeApi('containers.seal', 'containers:write');

        $result = (new SealContainer)($container);

        return $this->respondWith($result->toArray(), 'container');
    }

    /**
     * Unseal a sealed container so items can be modified. Does not cascade to
     * nested containers.
     */
    #[ApiResponse(200, 'Container unsealed')]
    public function unseal(Request $request, Container $container): JsonResponse
    {
        $this->authorizeApi('containers.seal', 'containers:write');

        $data = UnsealContainerData::from($request->validate(UnsealContainerData::rules()));

        $result = (new UnsealContainer)($container, $data);

        return $this->respondWith($result->toArray(), 'container');
    }

    /**
     * Manually dissolve a container, releasing all packed items and cascading
     * to nested containers.
     */
    #[ApiResponse(200, 'Container dissolved')]
    public function dissolve(Container $container): JsonResponse
    {
        $this->authorizeApi('containers.dissolve', 'containers:write');

        $result = (new DissolveContainer)($container);

        return $this->respondWith($result->toArray(), 'container');
    }

    /**
     * Atomically transfer a serialised item from a source container into this one.
     */
    #[ApiResponse(201, 'Item transferred')]
    public function transfer(Request $request, Container $container): JsonResponse
    {
        $this->authorizeApi('containers.transfer', 'containers:write');

        $data = TransferContainerItemData::from($request->validate(TransferContainerItemData::rules()));

        $item = (new TransferContainerItem)($container, $data);

        return $this->respondWith($item->toArray(), 'container_item', Response::HTTP_CREATED);
    }

    /**
     * Compare rolled-up contents against the product template (nested to max depth).
     */
    #[ApiResponse(200, 'Container compliance report')]
    public function compliance(Container $container): JsonResponse
    {
        $this->authorizeApi('containers.view', 'containers:read');

        $report = app(ContainerManifestService::class)->compliance($container);

        return response()->json([
            'compliance' => $report->toArray(),
        ]);
    }
}
