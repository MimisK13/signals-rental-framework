<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Presentation\Livewire;

use App\Domain\Assets\Actions\ConfirmAssetLost;
use App\Domain\Assets\Actions\MarkAssetMissing;
use App\Domain\Assets\Actions\RestoreAsset;
use App\Domain\Assets\Enums\AssetCondition;
use App\Domain\Assets\Enums\AssetLifecycleStatus;
use App\Domain\Assets\Repairs\Models\Repair;
use App\Domain\Availability\Shortages\ValueObjects\DispatchGateResult;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AllocateAsset;
use App\Domain\Projects\Opportunities\Actions\ClearAssetContainer;
use App\Domain\Projects\Opportunities\Actions\DeallocateAsset;
use App\Domain\Projects\Opportunities\Actions\DispatchAsset;
use App\Domain\Projects\Opportunities\Actions\DispatchBulkQuantity;
use App\Domain\Projects\Opportunities\Actions\FinaliseAssetReturnInspection;
use App\Domain\Projects\Opportunities\Actions\MarkAssetOnHire;
use App\Domain\Projects\Opportunities\Actions\PrepareAsset;
use App\Domain\Projects\Opportunities\Actions\QuickAllocateAssets;
use App\Domain\Projects\Opportunities\Actions\QuickBookOut;
use App\Domain\Projects\Opportunities\Actions\QuickPrepareAssets;
use App\Domain\Projects\Opportunities\Actions\ReturnAsset;
use App\Domain\Projects\Opportunities\Actions\ReturnBulkQuantity;
use App\Domain\Projects\Opportunities\Actions\RevertAssetPreparation;
use App\Domain\Projects\Opportunities\Actions\RevertAssetStatus;
use App\Domain\Projects\Opportunities\Actions\SetAssetContainer;
use App\Domain\Projects\Opportunities\Actions\SubstituteAsset;
use App\Domain\Projects\Opportunities\Data\AllocateAssetData;
use App\Domain\Projects\Opportunities\Data\BulkDispatchData;
use App\Domain\Projects\Opportunities\Data\BulkReturnData;
use App\Domain\Projects\Opportunities\Data\CheckAssetData;
use App\Domain\Projects\Opportunities\Data\DispatchAssetData;
use App\Domain\Projects\Opportunities\Data\QuickAllocateAssetsData;
use App\Domain\Projects\Opportunities\Data\QuickBookOutData;
use App\Domain\Projects\Opportunities\Data\QuickPrepareAssetsData;
use App\Domain\Projects\Opportunities\Data\ReturnAssetData;
use App\Domain\Projects\Opportunities\Data\RevertAssetStatusData;
use App\Domain\Projects\Opportunities\Data\SetAssetContainerData;
use App\Domain\Projects\Opportunities\Data\SubstituteAssetData;
use App\Domain\Projects\Opportunities\Enums\OpportunityState;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Presentation\Concerns\HasOpportunityActions;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Actions\DispatchContainer;
use App\Domain\Warehouse\Containers\Actions\ReturnKitContainer;
use App\Domain\Warehouse\Containers\Actions\StartRepackOnReturn;
use App\Domain\Warehouse\Containers\Actions\VerifyKitCheckin;
use App\Domain\Warehouse\Containers\Data\DispatchContainerData;
use App\Domain\Warehouse\Containers\Data\ReturnKitContainerData;
use App\Domain\Warehouse\Containers\Data\StartRepackOnReturnData;
use App\Domain\Warehouse\Containers\Enums\ContainerStatus;
use App\Domain\Warehouse\Containers\Exceptions\ContainerDispatchAmendmentRequiredException;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Services\ContainerBarcodeResolver;
use App\Domain\Warehouse\Containers\Services\ContainerManifestService;
use App\Domain\Warehouse\Containers\Services\RepackOnReturnEligibility;
use App\Enums\AssetAssignmentStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thunk\Verbs\Exceptions\EventNotValid;

/**
 * Opportunity Assets / Fulfilment tab — the industry-standard RMS asset-allocation
 * detail view (opportunity-lifecycle.md §5.4 / §7 / §11).
 *
 * The M5 milestone built the allocate -> prepare -> dispatch -> on-hire -> return ->
 * check actions + their API; this tab is the operator surface. It is a full-width
 * detail view with internal Livewire sub-tabs (a {@see $subTab} property — NOT
 * routes): Functions, Allocate, Prepare, Book out, Check in. Every sub-tab shares ONE
 * grouped asset table (grouped by product) below its controls.
 *
 *  - Functions — a multiselect-driven Action menu mapping to the batch RMS actions
 *    ({@see QuickAllocateAssets} / {@see QuickPrepareAssets} / {@see QuickBookOut} /
 *    {@see RevertAssetStatus} / {@see SetAssetContainer}). Sub-rent supplier /
 *    transfer-in / clear-transfer are the Phase-4 sub-hire/transfer surface and are
 *    rendered honestly DISABLED (never faked).
 *  - Allocate / Prepare / Book out / Check in — a scan bar that resolves a typed
 *    asset number to the action (the full barcode/scanning-abstraction layer is a
 *    later phase; a text + Enter input is the Phase-3 surface).
 *
 * Every mutation calls the SAME action classes the API controller uses (each
 * authorises `opportunities.edit` internally) — this component never self-HTTPs. The
 * per-asset legality is derived generically from the {@see AssetAssignmentStatus}
 * chain (no hardcoded named-status switch); the event guards remain the source of
 * truth (a 422 is caught + flashed).
 *
 * The dispatch path routes through the {@see App\Domain\Availability\Shortages\Services\DispatchShortageGate}
 * inside the action: a Block store policy throws a 422 (surfaced as the gate reason),
 * a WarnPartial policy proceeds and the action's `gateResult` exposes the held-item
 * metadata, which this component flashes after the dispatch.
 *
 * Reverb-live: subscribes to `availability.opportunity.{id}` so the table refreshes
 * when availability changes elsewhere (mirrors the M8-2 / M8-4c convention).
 */
#[Layout('components.layouts.app')] final class Assets extends Component
{
    use HasOpportunityActions;

    public Opportunity $opportunity;

    /** Whether the actor may mutate assignments (vs a read-only view). */
    public bool $canEdit = false;

    /** The active internal sub-tab (NOT a route): functions|allocate|prepare|book_out|check_in. */
    public string $subTab = 'functions';

    /** @var list<int|string> The asset ids ticked in the shared grouped table. */
    public array $selected = [];

    /** The chosen Functions Action menu operation, applied to {@see $selected}. */
    public string $bulkAction = '';

    /** The unified scan workspace's explicit serialized execution context. */
    public string $executionMode = 'prepare';

    /** @var array<int, string> */
    public array $bulkQuantities = [];

    public ?int $inspectionAssetId = null;

    public ?string $inspectionAssetLabel = null;

    public int $inspectionCondition = AssetCondition::Good->value;

    public ?string $inspectionNotes = null;

    public bool $sendToRepair = false;

    public bool $repairUnusable = false;

    /** The container asset number the Functions "Set container" action targets. */
    public ?string $bulkContainer = null;

    // --- Scan bar (Allocate / Prepare / Book out / Check in) ------------------

    /** The typed asset number the active sub-tab's scan bar will resolve + act on. */
    public ?string $scanAsset = null;

    /** The allocate sub-tab's quantity (whole units of the resolved product to book). */
    public ?string $scanQuantity = '1';

    /** The container asset number the scan bar nests the asset into after the action. */
    public ?string $scanContainer = null;

    /** @var array<string, mixed>|null */
    public ?array $containerDispatchPrompt = null;

    public ?string $pendingContainerDispatchUuid = null;

    /** Allocate sub-tab: allow allocating a free asset not pre-listed against any line. */
    public bool $freeScan = false;

    /** Allocate sub-tab: chain {@see PrepareAsset} immediately after allocating. */
    public bool $markPrepared = false;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('view', $opportunity);

        $this->opportunity = $opportunity->load('store');
        $this->canEdit = Gate::allows('opportunities.edit') && ! $opportunity->statusEnum()->isClosed();
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Assets');
    }

    /**
     * Live refresh when the opportunity's availability changes (a booking, return,
     * allocation, or stock movement elsewhere). The broadcast is a light signal; the
     * with() computed values re-read the projection on the next render so the
     * assignment list + status chips reflect the recalculated picture.
     */
    #[On('echo-private:availability.opportunity.{opportunity.id},.availability.changed')]
    public function onAvailabilityChanged(): void
    {
        $this->opportunity->refresh();
    }

    /** Switch the active internal sub-tab, clearing the scan bar between contexts. */
    public function setSubTab(string $subTab): void
    {
        $this->subTab = in_array($subTab, ['functions', 'allocate', 'prepare', 'book_out', 'check_in'], true)
            ? $subTab
            : 'functions';

        $this->resetScanBar();
    }

    /** Execute one scanner intent through the existing action-backed scan methods. */
    public function executeScan(): void
    {
        if (! in_array($this->executionMode, ['allocate', 'prepare', 'book_out', 'check_in'], true)) {
            $this->executionMode = 'prepare';
        }

        match ($this->executionMode) {
            'allocate' => $this->scanAllocate(),
            'prepare' => $this->scanPrepare(),
            'book_out' => $this->scanBookOut(),
            'check_in' => $this->scanCheckIn(),
        };

        $this->dispatch('warehouse-scan-ready');
    }

    protected function resetScanBar(): void
    {
        $this->scanAsset = null;
        $this->scanQuantity = '1';
        $this->scanContainer = null;
    }

    // -------------------------------------------------------------------------
    // Scan bar — each sub-tab resolves the typed asset number then acts on it.
    // NOTE: the scan fields are plain text inputs (resolve asset number -> action
    // on Enter/button); the full barcode/scanning-abstraction layer is a later
    // phase — a text + Enter input is the Phase-3 surface.
    // -------------------------------------------------------------------------

    /**
     * Allocate the scanned asset to a line. Resolves the typed asset number to a free
     * serialised stock level, matches it to a line of the same product (or, with Free
     * Scan, to any line missing capacity), and calls {@see AllocateAsset}. "Mark as
     * prepared" chains {@see PrepareAsset} after the allocation.
     */
    public function scanAllocate(): void
    {
        $number = trim((string) $this->scanAsset);

        if ($number === '') {
            return;
        }

        $this->run(function () use ($number): void {
            $level = $this->resolveFreeStockLevel($number);
            $item = $this->matchLineForStockLevel($level);

            $assignment = (new AllocateAsset)($item, AllocateAssetData::from([
                'stock_level_id' => $level->id,
            ]));

            if ($this->markPrepared) {
                $asset = $this->findAsset($assignment->id);
                (new PrepareAsset)($asset);
            }

            $this->applyScanContainer($assignment->id);
        }, __('Asset allocated.'));

        $this->resetScanBar();
    }

    /** Prepare the scanned (already-allocated) asset via {@see PrepareAsset}. */
    public function scanPrepare(): void
    {
        $this->actOnScannedAssignment(function (OpportunityItemAsset $asset): void {
            (new PrepareAsset)($asset);
            $this->applyScanContainer($asset->id);
        }, __('Asset prepared.'));
    }

    /**
     * Book the scanned asset out via {@see DispatchAsset} — routed through the §7.4
     * dispatch gate: a Block 422 is flashed, a WarnPartial proceeds and the held
     * items are surfaced.
     */
    public function scanBookOut(): void
    {
        $number = trim((string) $this->scanAsset);

        if ($number !== '' && $this->tryDispatchScannedContainer($number)) {
            return;
        }

        $action = new DispatchAsset;

        $this->actOnScannedAssignment(function (OpportunityItemAsset $asset) use ($action): void {
            $action($asset, DispatchAssetData::from([]));
            $this->applyScanContainer($asset->id);
        }, __('Asset booked out.'));

        $this->flashGateWarning($action->gateResult);
    }

    protected function tryDispatchScannedContainer(string $number): bool
    {
        $resolution = app(ContainerBarcodeResolver::class)->resolve($number);

        if ($resolution === null) {
            return false;
        }

        if ($resolution['type'] === 'container') {
            $container = $resolution['container'];
        } else {
            $container = $resolution['container'];

            if ($container === null) {
                $stockLevel = $resolution['stock_level'];
                $container = Container::query()
                    ->active()
                    ->where('serialised_item_id', $stockLevel->id)
                    ->where('status', ContainerStatus::Sealed)
                    ->first();
            }
        }

        if ($container === null) {
            return false;
        }

        if ($container->status !== ContainerStatus::Sealed) {
            if ($resolution['type'] === 'container') {
                session()->flash('error', __('Only sealed containers can be booked out.'));

                return true;
            }

            return false;
        }

        $this->dispatchContainer($container);

        return true;
    }

    public function dispatchContainerFromWorkspace(string $containerUuid): void
    {
        $container = $this->findOpportunityContainer($containerUuid);

        if ($container === null) {
            session()->flash('error', __('The container could not be found for this opportunity.'));

            return;
        }

        $this->dispatchContainer($container);
    }

    public function returnContainerFromWorkspace(string $containerUuid): void
    {
        $container = $this->findOpportunityContainer($containerUuid);

        if ($container === null) {
            session()->flash('error', __('The container could not be found for this opportunity.'));

            return;
        }

        $this->run(function () use ($container): void {
            (new ReturnKitContainer)($container, ReturnKitContainerData::from([]));
        }, __('Container returned.'));

        $this->resetScanBar();
    }

    public function verifyContainerFromWorkspace(string $containerUuid): void
    {
        $container = $this->findOpportunityContainer($containerUuid);

        if ($container === null) {
            session()->flash('error', __('The container could not be found for this opportunity.'));

            return;
        }

        $this->run(function () use ($container): void {
            (new VerifyKitCheckin)($container);
        }, __('Container check-in verified.'));
    }

    protected function dispatchContainer(Container $container): void
    {
        if (! Gate::check('dispatch', $container)) {
            session()->flash('error', __('You do not have permission to dispatch this container.'));

            return;
        }

        try {
            (new DispatchContainer)($container, DispatchContainerData::from([
                'opportunity_id' => $this->opportunity->id,
                'auto_add_missing' => true,
            ]));

            session()->flash('asset_status', __('Container dispatched.'));
            $this->resetScanBar();
            $this->opportunity->refresh();
        } catch (ContainerDispatchAmendmentRequiredException $e) {
            $this->containerDispatchPrompt = $e->payload();
            $this->pendingContainerDispatchUuid = $container->uuid;
            $this->dispatch('open-modal', 'container-dispatch-amendment');
        } catch (ValidationException $e) {
            session()->flash('error', $this->firstHumanError($e));
        } catch (AuthorizationException) {
            session()->flash('error', __('You do not have permission to perform this action.'));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $amendments
     */
    public function confirmContainerDispatchAmendments(array $amendments): void
    {
        if ($this->pendingContainerDispatchUuid === null) {
            return;
        }

        $container = Container::query()->where('uuid', $this->pendingContainerDispatchUuid)->firstOrFail();

        Gate::authorize('dispatch', $container);

        try {
            (new DispatchContainer)($container, DispatchContainerData::from([
                'opportunity_id' => $this->opportunity->id,
                'auto_add_missing' => true,
                'amendments' => $amendments,
            ]));

            $this->containerDispatchPrompt = null;
            $this->pendingContainerDispatchUuid = null;
            $this->dispatch('close-modal', 'container-dispatch-amendment');
            session()->flash('asset_status', __('Container dispatched with amendments.'));
            $this->opportunity->refresh();
        } catch (ContainerDispatchAmendmentRequiredException $e) {
            $this->containerDispatchPrompt = $e->payload();
        } catch (ValidationException $e) {
            session()->flash('error', $this->firstHumanError($e));
        }
    }

    public function dismissContainerDispatchPrompt(): void
    {
        $this->containerDispatchPrompt = null;
        $this->pendingContainerDispatchUuid = null;
        $this->dispatch('close-modal', 'container-dispatch-amendment');
    }

    /** Check the scanned asset back in via {@see ReturnAsset} or start repack-on-return. */
    public function scanCheckIn(): void
    {
        $number = trim((string) $this->scanAsset);

        if ($number === '') {
            return;
        }

        if ($this->tryStartRepackFromCheckIn($number)) {
            return;
        }

        if ($this->tryReturnScannedContainer($number)) {
            return;
        }

        $this->actOnScannedAssignment(function (OpportunityItemAsset $asset): void {
            (new ReturnAsset)($asset, ReturnAssetData::from([]));
            $this->applyScanContainer($asset->id);
        }, __('Asset checked in.'));
    }

    protected function tryStartRepackFromCheckIn(string $assetNumber): bool
    {
        $stockLevels = StockLevel::query()
            ->where(fn ($query) => $query
                ->where('asset_number', $assetNumber)
                ->orWhere('serial_number', $assetNumber)
                ->orWhere('barcode', $assetNumber))
            ->get();

        if ($stockLevels->count() > 1) {
            throw ValidationException::withMessages([
                'asset' => __('Ambiguous identifier: ":number" matches multiple physical assets.', ['number' => $assetNumber]),
            ]);
        }

        $stockLevel = $stockLevels->first();
        $assignment = $stockLevel === null
            ? null
            : OpportunityItemAsset::query()
                ->where('stock_level_id', $stockLevel->id)
                ->whereHas('item', fn ($query) => $query->where('opportunity_id', $this->opportunity->id))
                ->with('stockLevel.product')
                ->first();

        if ($assignment === null) {
            return false;
        }

        $housing = $assignment->stockLevel;

        if ($housing === null) {
            return false;
        }

        $eligibility = app(RepackOnReturnEligibility::class);

        if (! $eligibility->isEligibleHousing($housing)
            || ! $eligibility->isCheckedOutOnOpportunity($housing, $this->opportunity->id)) {
            return false;
        }

        Gate::authorize('create', Container::class);
        Gate::authorize('opportunities.edit');

        $result = (new StartRepackOnReturn)(StartRepackOnReturnData::from([
            'opportunity_id' => $this->opportunity->id,
            'serialised_item_id' => $housing->id,
        ]));

        $this->resetScanBar();

        $this->redirect(route('containers.repack', [
            'container' => $result->container->uuid,
            'opportunity' => $this->opportunity->id,
        ]), navigate: true);

        return true;
    }

    /**
     * Resolve the scan bar's typed asset number to one of this opportunity's existing
     * assignments and run $callback against it. Shared by prepare/book-out/check-in.
     *
     * @param  \Closure(OpportunityItemAsset): void  $callback
     */
    protected function actOnScannedAssignment(\Closure $callback, string $success): void
    {
        $number = trim((string) $this->scanAsset);

        if ($number === '') {
            return;
        }

        $this->run(function () use ($number, $callback): void {
            $callback($this->resolveAssignment($number));
        }, $success);

        $this->resetScanBar();
    }

    /** Return a scanned dispatched kit/hybrid container through its canonical action. */
    protected function tryReturnScannedContainer(string $number): bool
    {
        $resolution = app(ContainerBarcodeResolver::class)->resolve($number);

        if ($resolution === null) {
            return false;
        }

        $container = $resolution['container'];

        if ($container === null) {
            return false;
        }

        if ($container->status !== ContainerStatus::Dispatched) {
            if ($resolution['type'] === 'container') {
                session()->flash('error', __('Only dispatched containers can be checked in.'));

                return true;
            }

            return false;
        }

        if (! $container->availabilityMode()->isBookableKit()) {
            session()->flash('error', __('Transport containers are returned through the repack workflow.'));

            return true;
        }

        $this->returnContainerFromWorkspace($container->uuid);

        return true;
    }

    /**
     * Nest the just-acted asset into the scanned container, if one was typed, via the
     * {@see SetAssetContainer} M5 action (resolving the container asset number to its
     * stock level). Best-effort: a container miss is flashed but doesn't undo the
     * primary action (it already committed).
     */
    protected function applyScanContainer(int $assignmentId): void
    {
        $container = trim((string) $this->scanContainer);

        if ($container === '') {
            return;
        }

        $level = StockLevel::query()
            ->where('asset_number', $container)
            ->orWhere('serial_number', $container)
            ->first();

        if ($level === null) {
            throw ValidationException::withMessages([
                'container' => __('No container was found for ":number".', ['number' => $container]),
            ]);
        }

        (new SetAssetContainer)($this->findAsset($assignmentId), SetAssetContainerData::from([
            'container_stock_level_id' => $level->id,
        ]));
    }

    // -------------------------------------------------------------------------
    // Functions sub-tab — the multiselect Action menu (batch RMS actions)
    // -------------------------------------------------------------------------

    /** Tick / untick every asset row (the table's header checkbox). */
    public function toggleSelectAll(bool $checked): void
    {
        $this->selected = $checked ? array_map('strval', $this->allAssetIds()) : [];
    }

    /**
     * Run the chosen Functions Action against the selected rows. Each branch maps to a
     * real M5 batch/single action; the Phase-4 sub-hire/transfer operations are not
     * selectable (rendered disabled), so they can never reach here.
     */
    public function runBulkAction(): void
    {
        $ids = $this->selectedAssetIds();

        if ($this->bulkAction === '' || $ids === []) {
            return;
        }

        $action = $this->bulkAction;

        if ($action === 'book_out') {
            $this->runBulkBookOut($ids);
            $this->afterBulk();

            return;
        }

        $this->run(function () use ($action, $ids): void {
            match ($action) {
                'auto_allocate' => $this->runBulkAutoAllocate($ids),
                'prepare' => (new QuickPrepareAssets)($this->opportunity, QuickPrepareAssetsData::from([
                    'asset_ids' => $ids,
                ])),
                'revert_status' => $this->runBulkRevert($ids),
                'set_container' => $this->runBulkSetContainer($ids),
                default => throw ValidationException::withMessages([
                    'action' => __('That action is not available.'),
                ]),
            };
        }, $this->bulkSuccessMessage($action));

        $this->afterBulk();
    }

    /**
     * Book the selected assets out in one atomic {@see QuickBookOut} commit, routed
     * through the dispatch gate (a Block 422 rolls the batch back + is flashed; a
     * WarnPartial proceeds and the held items are surfaced).
     */
    /** @param list<int> $ids */
    protected function runBulkBookOut(array $ids): void
    {
        $action = new QuickBookOut;

        $this->run(function () use ($action, $ids): void {
            $action($this->opportunity, QuickBookOutData::from(['asset_ids' => $ids]));
        }, __('Assets booked out.'));

        $this->flashGateWarning($action->gateResult);
    }

    /**
     * Re-allocate the selected assets to their own lines (a no-op for already
     * allocated assets is impossible — they are; this maps "Auto-allocate" to the RMS
     * quick_allocate of every selected asset's current stock level to its line).
     */
    /** @param list<int> $ids */
    protected function runBulkAutoAllocate(array $ids): void
    {
        $allocations = $this->opportunity->items
            ->flatMap->assets
            ->whereIn('id', $ids)
            ->filter(fn (OpportunityItemAsset $asset): bool => $asset->stock_level_id !== null)
            ->map(fn (OpportunityItemAsset $asset): array => [
                'opportunity_item_id' => $asset->opportunity_item_id,
                'stock_level_id' => $asset->stock_level_id,
            ])
            ->values()
            ->all();

        if ($allocations === []) {
            return;
        }

        (new QuickAllocateAssets)($this->opportunity, QuickAllocateAssetsData::from([
            'allocations' => $allocations,
        ]));
    }

    /** Revert each selected asset one legal step back along the chain. */
    /** @param list<int> $ids */
    protected function runBulkRevert(array $ids): void
    {
        foreach ($ids as $id) {
            $asset = $this->findAsset((int) $id);
            $previous = $this->previousStatus($asset->status);

            if ($previous === null) {
                continue;
            }

            (new RevertAssetStatus)($asset, RevertAssetStatusData::from([
                'revert_to' => $previous->value,
            ]));
        }
    }

    /** Nest each selected asset into the typed container via {@see SetAssetContainer}. */
    /** @param list<int> $ids */
    protected function runBulkSetContainer(array $ids): void
    {
        $number = trim((string) $this->bulkContainer);

        if ($number === '') {
            throw ValidationException::withMessages([
                'container' => __('Enter the container asset number to set.'),
            ]);
        }

        $level = StockLevel::query()
            ->where('asset_number', $number)
            ->orWhere('serial_number', $number)
            ->first();

        if ($level === null) {
            throw ValidationException::withMessages([
                'container' => __('No container was found for ":number".', ['number' => $number]),
            ]);
        }

        foreach ($ids as $id) {
            (new SetAssetContainer)($this->findAsset((int) $id), SetAssetContainerData::from([
                'container_stock_level_id' => $level->id,
            ]));
        }
    }

    protected function afterBulk(): void
    {
        $this->bulkAction = '';
        $this->bulkContainer = null;
        $this->selected = [];
        $this->opportunity->refresh();
    }

    protected function bulkSuccessMessage(string $action): string
    {
        return match ($action) {
            'auto_allocate' => __('Assets allocated.'),
            'prepare' => __('Assets prepared.'),
            'revert_status' => __('Asset statuses reverted.'),
            'set_container' => __('Container set.'),
            default => __('Done.'),
        };
    }

    // -------------------------------------------------------------------------
    // Per-row actions (the chevron menu) — single-asset M5 actions
    // -------------------------------------------------------------------------

    public function deallocate(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new DeallocateAsset)($this->findAsset($assetId));
        }, __('Asset deallocated.'));
    }

    public function prepare(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new PrepareAsset)($this->findAsset($assetId));
        }, __('Asset prepared.'));
    }

    public function revertPreparation(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new RevertAssetPreparation)($this->findAsset($assetId));
        }, __('Preparation reverted.'));
    }

    /**
     * Book a serialised asset out. The dispatch gate runs inside the action: a Block
     * store policy throws a 422 (flashed as the gate reason), a WarnPartial store
     * policy proceeds and the action's gateResult carries the held-item metadata,
     * surfaced here as a warning flash.
     */
    public function dispatchAsset(int $assetId): void
    {
        $action = new DispatchAsset;

        $this->run(function () use ($assetId, $action): void {
            $action($this->findAsset($assetId), DispatchAssetData::from([]));
        }, __('Asset booked out.'));

        $this->flashGateWarning($action->gateResult);
    }

    public function markOnHire(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new MarkAssetOnHire)($this->findAsset($assetId));
        }, __('Asset marked on hire.'));
    }

    public function returnAsset(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new ReturnAsset)($this->findAsset($assetId), ReturnAssetData::from([]));
        }, __('Asset checked in.'));
    }

    /** Mark a physically outstanding Asset missing through its canonical lifecycle action. */
    public function markMissing(int $assetId): void
    {
        $assignment = $this->findAsset($assetId);

        if (! in_array($assignment->status, [AssetAssignmentStatus::Dispatched, AssetAssignmentStatus::OnHire], true)) {
            session()->flash('error', __('Only an Asset that is still out can be marked missing.'));

            return;
        }

        $asset = $assignment->load('stockLevel.asset')->stockLevel?->asset;

        if ($asset === null) {
            session()->flash('error', __('The physical Asset could not be resolved for this assignment.'));

            return;
        }

        $this->run(function () use ($asset): void {
            (new MarkAssetMissing)($asset, ['reason' => __('Marked missing from rental return.')]);
        }, __('Asset marked missing.'));
    }

    /** Confirm an already-missing Asset as lost through its canonical lifecycle action. */
    public function confirmLost(int $assetId): void
    {
        $assignment = $this->findAsset($assetId);
        $asset = $assignment->load('stockLevel.asset')->stockLevel?->asset;

        if ($asset?->lifecycleStatus() !== AssetLifecycleStatus::Missing) {
            session()->flash('error', __('Only a missing Asset can be confirmed lost.'));

            return;
        }

        $this->run(function () use ($asset): void {
            (new ConfirmAssetLost)($asset, ['reason' => __('Confirmed lost from rental return.')]);
        }, __('Asset confirmed lost.'));
    }

    /** Restore a Missing/Lost Asset without fabricating a physical return. */
    public function restoreAsset(int $assetId): void
    {
        $assignment = $this->findAsset($assetId);
        $asset = $assignment->load('stockLevel.asset')->stockLevel?->asset;

        if ($asset === null || ! in_array($asset->lifecycleStatus(), [AssetLifecycleStatus::Missing, AssetLifecycleStatus::Lost], true)) {
            session()->flash('error', __('Only a missing or lost Asset can be restored.'));

            return;
        }

        $storeId = $assignment->stockLevel?->store_id;

        $this->run(function () use ($asset, $storeId): void {
            (new RestoreAsset)($asset, array_filter([
                'store_id' => $storeId,
                'reason' => __('Asset restored after rental return review.'),
            ], static fn (mixed $value): bool => $value !== null));
        }, __('Asset restored. It still requires the normal physical check-in path.'));
    }

    /** Open the return inspection for a physically returned asset. */
    public function inspectAsset(int $assetId): void
    {
        $asset = $this->findAsset($assetId);

        if ($asset->status !== AssetAssignmentStatus::CheckedIn) {
            session()->flash('error', __('Only checked-in assets can be inspected.'));

            return;
        }

        $asset->loadMissing('stockLevel');
        $this->inspectionAssetId = $asset->id;
        $this->inspectionAssetLabel = data_get($asset, 'stockLevel.asset_number')
            ?? data_get($asset, 'stockLevel.serial_number')
            ?? data_get($asset, 'stockLevel.item_name')
            ?? '#'.$asset->id;
        $this->inspectionCondition = AssetCondition::Good->value;
        $this->inspectionNotes = null;
        $this->sendToRepair = false;
        $this->repairUnusable = false;
        $this->dispatch('open-modal', 'inspect-asset');
    }

    public function updatedInspectionCondition(int|string $condition): void
    {
        if ((int) $condition !== AssetCondition::Damaged->value) {
            $this->sendToRepair = false;
            $this->repairUnusable = false;
        }
    }

    /** Finalise the return inspection through the canonical CheckAsset action. */
    public function finaliseInspection(): void
    {
        $this->validate([
            'inspectionAssetId' => ['required', 'integer'],
            'inspectionCondition' => [
                'required',
                'integer',
                Rule::in([
                    AssetCondition::Good->value,
                    AssetCondition::Damaged->value,
                ]),
            ],
            'inspectionNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->sendToRepair && $this->inspectionCondition !== AssetCondition::Damaged->value) {
            $this->addError('sendToRepair', __('Only a damaged return can be sent to repair.'));

            return;
        }

        if ($this->repairUnusable && ! $this->sendToRepair) {
            $this->addError('repairUnusable', __('Select Send to repair before marking the equipment unusable.'));

            return;
        }

        if ($this->sendToRepair && ! $this->canCreateRepair()) {
            $this->addError('sendToRepair', __('You do not have permission to create repairs.'));

            return;
        }

        $assetId = $this->inspectionAssetId;
        $condition = $this->inspectionCondition;
        $notes = $this->inspectionNotes;

        $message = $this->sendToRepair
            ? ($this->repairUnusable
                ? __('Asset finalised and sent to repair as unusable.')
                : __('Asset finalised and sent to repair.'))
            : __('Asset finalised.');

        $sendToRepair = $this->sendToRepair;
        $repairUnusable = $this->repairUnusable;
        $completed = $this->run(function () use ($assetId, $condition, $notes, $sendToRepair, $repairUnusable): void {
            (new FinaliseAssetReturnInspection)($this->findAsset($assetId), CheckAssetData::from([
                'condition' => $condition,
                'damage_notes' => $notes,
            ]), $sendToRepair, $repairUnusable);
        }, $message);

        if (! $completed) {
            return;
        }

        $this->reset(['inspectionAssetId', 'inspectionAssetLabel', 'inspectionCondition', 'inspectionNotes']);
        $this->inspectionCondition = AssetCondition::Good->value;
        $this->sendToRepair = false;
        $this->repairUnusable = false;
        $this->dispatch('close-modal', 'inspect-asset');
    }

    public function canCreateRepair(): bool
    {
        if (! Gate::allows('create', Repair::class) || $this->inspectionAssetId === null) {
            return false;
        }

        $storeId = $this->findAsset($this->inspectionAssetId)->stockLevel?->store_id;

        return $storeId !== null
            && Gate::allows('create', new Repair(['store_id' => $storeId]));
    }

    /** Backward-compatible entry point for existing action bindings. */
    public function check(int $assetId): void
    {
        $this->inspectAsset($assetId);
    }

    /** Dispatch a partial quantity on a bulk line through the canonical action. */
    public function dispatchBulk(int $itemId): void
    {
        $item = $this->findBulkItem($itemId);
        $quantity = trim((string) ($this->bulkQuantities[$itemId] ?? ''));

        if ($quantity === '') {
            session()->flash('error', __('Enter a quantity to dispatch.'));

            return;
        }

        $action = new DispatchBulkQuantity;

        $this->run(function () use ($action, $item, $quantity): void {
            $action($item, BulkDispatchData::from(['quantity' => $quantity]));
        }, __('Bulk quantity dispatched.'));

        $this->flashGateWarning($action->gateResult);
        $this->bulkQuantities[$itemId] = '';
    }

    /** Return a partial quantity on a bulk line through the canonical action. */
    public function returnBulk(int $itemId): void
    {
        $item = $this->findBulkItem($itemId);
        $quantity = trim((string) ($this->bulkQuantities[$itemId] ?? ''));

        if ($quantity === '') {
            session()->flash('error', __('Enter a quantity to return.'));

            return;
        }

        $this->run(function () use ($item, $quantity): void {
            (new ReturnBulkQuantity)($item, BulkReturnData::from(['quantity' => $quantity]));
        }, __('Bulk quantity returned.'));

        $this->bulkQuantities[$itemId] = '';
    }

    /** Remove an asset assignment from its container via {@see ClearAssetContainer}. */
    public function clearContainer(int $assetId): void
    {
        $this->run(function () use ($assetId): void {
            (new ClearAssetContainer)($this->findAsset($assetId));
        }, __('Container cleared.'));
    }

    public function substitute(int $assetId): void
    {
        $this->substituteAssetId = $assetId;
        $this->substituteStockLevelId = null;
        $this->substituteReason = null;
    }

    /**
     * Revert an asset one step back along the dispatch/return chain (to correct a
     * mistaken scan). The legal previous status is derived from the chain.
     */
    public function revertStatus(int $assetId): void
    {
        $asset = $this->findAsset($assetId);
        $previous = $this->previousStatus($asset->status);

        if ($previous === null) {
            return;
        }

        $this->run(function () use ($asset, $previous): void {
            (new RevertAssetStatus)($asset, RevertAssetStatusData::from([
                'revert_to' => $previous->value,
            ]));
        }, __('Asset status reverted.'));
    }

    // --- Substitute modal -----------------------------------------------------

    public ?int $substituteAssetId = null;

    public ?int $substituteStockLevelId = null;

    public ?string $substituteReason = null;

    public function confirmSubstitute(): void
    {
        if ($this->substituteAssetId === null || $this->substituteStockLevelId === null) {
            return;
        }

        $this->run(function (): void {
            (new SubstituteAsset)($this->findAsset($this->substituteAssetId), SubstituteAssetData::from([
                'new_stock_level_id' => $this->substituteStockLevelId,
                'reason' => $this->substituteReason,
            ]));
        }, __('Asset substituted.'));

        $this->substituteAssetId = null;
        $this->substituteStockLevelId = null;
        $this->substituteReason = null;
        $this->dispatch('asset-modal-close', name: 'substitute-asset');
    }

    // -------------------------------------------------------------------------
    // Legality (derived from the status chain — never a hardcoded matrix)
    // -------------------------------------------------------------------------

    /**
     * Whether the opportunity is an Order — dispatch/return/on-hire are only legal on
     * an order (the AssetDispatched guard rejects dispatch on a quote).
     */
    public function isOrder(): bool
    {
        return $this->opportunity->state === OpportunityState::Order;
    }

    /**
     * The legal next fulfilment actions for an asset in $status, as a list of
     * {action, label, variant, confirm} descriptors. Derived generically from the
     * status chain; the event guards remain the source of truth.
     *
     * @return list<array{action: string, label: string, variant: string, confirm: string|null}>
     */
    public function assetActions(
        AssetAssignmentStatus $status,
        ?AssetLifecycleStatus $lifecycleStatus = null,
        ?int $storeId = null,
    ): array {
        $order = $this->isOrder();
        $canAdjustLifecycle = Gate::allows('stock.adjust')
            && auth()->user()?->canAccessStore($storeId);
        $actions = [];

        if ($lifecycleStatus === AssetLifecycleStatus::Missing) {
            if ($canAdjustLifecycle) {
                $actions[] = $this->descriptor('confirmLost', __('Confirm lost'), 'danger', __('Confirm this Asset as lost?'));
                $actions[] = $this->descriptor('restoreAsset', __('Restore'), 'ghost', __('Restore this Asset lifecycle? It will still require the normal physical check-in path.'));
            }

            return $actions;
        }

        if ($lifecycleStatus === AssetLifecycleStatus::Lost) {
            if ($canAdjustLifecycle) {
                $actions[] = $this->descriptor('restoreAsset', __('Restore'), 'ghost', __('Restore this Asset lifecycle? It will still require the normal physical check-in path.'));
            }

            return $actions;
        }

        switch ($status) {
            case AssetAssignmentStatus::Allocated:
                $actions[] = $this->descriptor('prepare', __('Prepare'), 'outline-blue');
                if ($order) {
                    $actions[] = $this->descriptor('dispatchAsset', __('Book out'), 'outline-green');
                }
                $actions[] = $this->descriptor('substitute', __('Substitute'), 'ghost');
                $actions[] = $this->descriptor('deallocate', __('Deallocate'), 'ghost', __('Release this asset from the line?'));
                break;

            case AssetAssignmentStatus::Prepared:
                if ($order) {
                    $actions[] = $this->descriptor('dispatchAsset', __('Book out'), 'outline-green');
                }
                $actions[] = $this->descriptor('revertPreparation', __('Un-prepare'), 'ghost');
                $actions[] = $this->descriptor('substitute', __('Substitute'), 'ghost');
                break;

            case AssetAssignmentStatus::Dispatched:
                $actions[] = $this->descriptor('markOnHire', __('Mark on hire'), 'outline-blue');
                $actions[] = $this->descriptor('returnAsset', __('Check in'), 'outline-green');
                if ($canAdjustLifecycle) {
                    $actions[] = $this->descriptor('markMissing', __('Mark missing'), 'danger', __('Mark this Asset as missing?'));
                }
                $actions[] = $this->descriptor('revertStatus', __('Revert'), 'ghost', __('Revert this asset one step back?'));
                break;

            case AssetAssignmentStatus::OnHire:
                $actions[] = $this->descriptor('returnAsset', __('Check in'), 'outline-green');
                if ($canAdjustLifecycle) {
                    $actions[] = $this->descriptor('markMissing', __('Mark missing'), 'danger', __('Mark this Asset as missing?'));
                }
                $actions[] = $this->descriptor('revertStatus', __('Revert'), 'ghost', __('Revert this asset one step back?'));
                break;

            case AssetAssignmentStatus::CheckedIn:
                $actions[] = $this->descriptor('inspectAsset', __('Inspect / Finalise'), 'outline-green');
                $actions[] = $this->descriptor('revertStatus', __('Revert'), 'ghost', __('Revert this asset one step back?'));
                break;

            case AssetAssignmentStatus::Finalised:
                // Terminal — no further fulfilment action.
                break;
        }

        return $actions;
    }

    /**
     * The status one step back along the dispatch/return chain, or null when the asset
     * is at the start of the revertable range.
     */
    public function previousStatus(AssetAssignmentStatus $status): ?AssetAssignmentStatus
    {
        return match ($status) {
            AssetAssignmentStatus::Dispatched => AssetAssignmentStatus::Prepared,
            AssetAssignmentStatus::OnHire => AssetAssignmentStatus::Dispatched,
            AssetAssignmentStatus::CheckedIn => AssetAssignmentStatus::OnHire,
            AssetAssignmentStatus::Finalised => AssetAssignmentStatus::CheckedIn,
            default => null,
        };
    }

    /**
     * @return array{action: string, label: string, variant: string, confirm: string|null}
     */
    protected function descriptor(string $action, string $label, string $variant, ?string $confirm = null): array
    {
        return ['action' => $action, 'label' => $label, 'variant' => $variant, 'confirm' => $confirm];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Run an assignment mutation, catching the auth/422/404 failures the action
     * classes raise (an illegal transition is a 422) and flashing the first message.
     * The projection is re-read so the next render reflects the new state.
     *
     * @param  \Closure(): void  $action
     */
    protected function run(\Closure $action, string $success): bool
    {
        if (! $this->canEdit) {
            session()->flash('error', __('This opportunity is closed or you cannot edit it.'));

            return false;
        }

        $completed = false;

        try {
            $action();
            session()->flash('asset_status', $success);
            $completed = true;
        } catch (AuthorizationException) {
            session()->flash('error', __('You do not have permission to perform this action.'));
        } catch (NotFoundHttpException) {
            session()->flash('error', __('The scanned asset could not be found for this opportunity.'));
        } catch (ValidationException $e) {
            session()->flash('error', $this->firstHumanError($e));
        } catch (EventNotValid $e) {
            // An illegal transition (e.g. booking out a non-prepared asset, dispatch
            // on a quote) — the Verbs event guard is the source of truth; its message
            // is operator-readable, so surface it directly.
            session()->flash('error', $e->getMessage() ?: __('This action could not be completed.'));
        }

        $this->opportunity->refresh();

        return $completed;
    }

    /**
     * The first operator-readable validation message, skipping the machine-readable
     * `code` key the dispatch gate sets alongside its human `shortages` message (so a
     * Block surfaces "Dispatch is blocked: …", not "dispatch_block").
     */
    protected function firstHumanError(ValidationException $e): string
    {
        $errors = $e->errors();

        $human = Arr::except($errors, ['code']);
        $messages = collect($human === [] ? $errors : $human)->flatten();

        return $messages->first() ?? __('This action could not be completed.');
    }

    /**
     * Flash the held-item metadata from a WarnPartial dispatch so the operator sees
     * what was held back (the same metadata the API surfaces in its response meta).
     */
    protected function flashGateWarning(?DispatchGateResult $gateResult): void
    {
        if ($gateResult === null || ! $gateResult->warned()) {
            return;
        }

        /** @var list<array<string, mixed>> $heldItems */
        $heldItems = $gateResult->toHeldItemsMeta()['held_items'] ?? [];
        $held = collect($heldItems)
            ->map(fn (array $badge): string => (string) ($badge['product_name'] ?? $badge['label'] ?? __('a line')))
            ->filter()
            ->unique()
            ->implode(', ');

        session()->flash('asset_warning', $held === ''
            ? __('Dispatched with a partial-shipment warning — some items were held back.')
            : __('Dispatched as a partial shipment — held back: :items.', ['items' => $held]));
    }

    /**
     * Resolve a typed asset number to one of this opportunity's existing assignments
     * (the prepare / book-out / check-in scan path).
     */
    protected function resolveAssignment(string $number): OpportunityItemAsset
    {
        $stockLevels = StockLevel::query()
            ->where(fn ($query) => $query
                ->where('asset_number', $number)
                ->orWhere('serial_number', $number)
                ->orWhere('barcode', $number))
            ->get();

        if ($stockLevels->count() > 1) {
            throw ValidationException::withMessages([
                'asset' => __('Ambiguous identifier: ":number" matches multiple physical assets.', ['number' => $number]),
            ]);
        }

        $stockLevel = $stockLevels->first();
        $asset = $stockLevel === null
            ? null
            : OpportunityItemAsset::query()
                ->where('stock_level_id', $stockLevel->id)
                ->whereHas('item', fn ($query) => $query->where('opportunity_id', $this->opportunity->id))
                ->first();

        if ($asset === null) {
            if ($stockLevel === null) {
                throw ValidationException::withMessages([
                    'asset' => __('Unknown asset: ":number".', ['number' => $number]),
                ]);
            }

            if ((int) $stockLevel->store_id !== (int) $this->opportunity->store_id) {
                throw ValidationException::withMessages([
                    'asset' => __('This asset belongs to another Store and was not changed.'),
                ]);
            }

            $assignedElsewhere = OpportunityItemAsset::query()
                ->where('stock_level_id', $stockLevel->id)
                ->whereHas('item', fn ($query) => $query->where('opportunity_id', '!=', $this->opportunity->id))
                ->exists();

            throw ValidationException::withMessages([
                'asset' => $assignedElsewhere
                    ? __('This asset belongs to another Order and was not changed.')
                    : __('Asset ":number" exists but is not assigned to this Order.', ['number' => $number]),
            ]);
        }

        return $asset;
    }

    /**
     * Resolve a typed asset number to a free serialised stock level (the allocate scan
     * path). With Free Scan off the level must belong to a product already on a line.
     */
    protected function resolveFreeStockLevel(string $number): StockLevel
    {
        $levels = StockLevel::query()
            ->serialized()
            ->where(fn ($q) => $q
                ->where('asset_number', $number)
                ->orWhere('serial_number', $number)
                ->orWhere('barcode', $number))
            ->get();

        if ($levels->count() > 1) {
            throw ValidationException::withMessages([
                'asset' => __('Ambiguous identifier: ":number" matches multiple physical assets.', ['number' => $number]),
            ]);
        }

        $level = $levels->first();

        if ($level === null) {
            throw ValidationException::withMessages([
                'asset' => __('No serialised asset numbered ":number" was found.', ['number' => $number]),
            ]);
        }

        return $level;
    }

    /**
     * Match a free stock level to the line it should be allocated against: the line of
     * the same product that still has capacity. With Free Scan on, fall back to ANY
     * line with capacity (best-effort) when the product does not match a line.
     */
    protected function matchLineForStockLevel(StockLevel $level): OpportunityItem
    {
        $candidate = $this->lineWithCapacity(fn (OpportunityItem $item): bool => $item->isProductBacked()
            && $item->itemable_id === $level->product_id);

        if ($candidate === null && $this->freeScan) {
            $candidate = $this->lineWithCapacity(fn (OpportunityItem $item): bool => $this->itemIsSerialised($item));
        }

        if ($candidate === null) {
            throw ValidationException::withMessages([
                'asset' => __('No line item with remaining capacity matches that asset.'),
            ]);
        }

        return $candidate;
    }

    /**
     * The first line (by display order) satisfying $predicate that still has free
     * allocation capacity (allocated assets < requested quantity).
     *
     * @param  \Closure(OpportunityItem): bool  $predicate
     */
    protected function lineWithCapacity(\Closure $predicate): ?OpportunityItem
    {
        return $this->opportunity->items
            ->sortBy('path')
            ->first(fn (OpportunityItem $item): bool => $predicate($item)
                && $item->assets->count() < (int) ceil((float) $item->quantity));
    }

    protected function findAsset(int $assetId): OpportunityItemAsset
    {
        $asset = OpportunityItemAsset::query()
            ->whereKey($assetId)
            ->whereHas('item', fn ($q) => $q->where('opportunity_id', $this->opportunity->id))
            ->first();

        if ($asset === null) {
            throw ValidationException::withMessages(['asset' => __('The asset assignment could not be found.')]);
        }

        return $asset;
    }

    protected function findBulkItem(int $itemId): OpportunityItem
    {
        $item = OpportunityItem::query()
            ->whereKey($itemId)
            ->where('opportunity_id', $this->opportunity->id)
            ->first();

        if ($item === null || $this->itemIsSerialised($item)) {
            throw ValidationException::withMessages([
                'item' => __('That line is not available for bulk quantity execution.'),
            ]);
        }

        return $item;
    }

    /**
     * The asset ids the operator ticked, intersected with the ids actually on the
     * opportunity (so a stale selection can never act on a foreign asset).
     *
     * @return list<int>
     */
    protected function selectedAssetIds(): array
    {
        $valid = array_flip($this->allAssetIds());

        return collect($this->selected)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => isset($valid[$id]))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function allAssetIds(): array
    {
        return $this->opportunity->items
            ->flatMap->assets
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Whether a line is serialised (driven by the product's stock method) and so is
     * fulfilled by allocating specific assets, vs a bulk line dispatched by quantity.
     */
    protected function itemIsSerialised(OpportunityItem $item): bool
    {
        if (! $item->isProductBacked() || $item->itemable_id === null) {
            return $item->assets->isNotEmpty();
        }

        $product = $this->productCache()[$item->itemable_id] ?? null;

        return $product?->stock_method === StockMethod::Serialised;
    }

    /**
     * Cache of the products referenced by the opportunity's lines (one query).
     *
     * @return array<int, Product>
     */
    protected function productCache(): array
    {
        return once(function (): array {
            $ids = $this->opportunity->items
                ->filter(fn (OpportunityItem $item): bool => $item->isProductBacked())
                ->pluck('itemable_id')
                ->filter()
                ->unique()
                ->all();

            if ($ids === []) {
                return [];
            }

            return Product::query()->whereIn('id', $ids)->get()->keyBy('id')->all();
        });
    }

    /**
     * Free serialised stock levels of a line's product for the substitute picker.
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    protected function freeAssetsForItem(?int $itemId): Collection
    {
        if ($itemId === null) {
            return collect();
        }

        $item = $this->opportunity->items->firstWhere('id', $itemId);

        if ($item === null || ! $item->isProductBacked() || $item->itemable_id === null) {
            return collect();
        }

        return StockLevel::query()
            ->where('product_id', $item->itemable_id)
            ->serialized()
            ->available()
            ->orderBy('asset_number')
            ->limit(100)
            ->get()
            ->map(fn (StockLevel $level): array => [
                'id' => $level->id,
                'label' => $level->asset_number
                    ?? $level->serial_number
                    ?? $level->item_name
                    ?? (string) $level->id,
            ]);
    }

    /**
     * The line item id the substitute modal's asset belongs to (so its free-asset
     * options can be loaded), or null when the modal is closed.
     */
    protected function substituteAssetItemId(): ?int
    {
        if ($this->substituteAssetId === null) {
            return null;
        }

        return $this->opportunity->items
            ->flatMap->assets
            ->firstWhere('id', $this->substituteAssetId)
            ?->opportunity_item_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        // Keep relationship loading idempotent when Livewire evaluates the view
        // data more than once during a render/morph cycle.
        $this->opportunity->loadMissing([
            'items' => fn ($q) => $q->orderBy('path')->orderBy('id'),
            'items.assets' => fn ($q) => $q->with(['stockLevel.asset', 'container'])->orderBy('id'),
            'items.assets.stockLevel.asset',
            'items.assets.container',
        ]);
        $containers = $this->opportunityContainers();

        return [
            ...$this->opportunityActionData(),

            'groups' => $this->buildGroups(),
            'containers' => $containers,
            'containerProgress' => $this->containerProgress($containers),
            'freeAssets' => $this->freeAssetsForItem($this->substituteAssetItemId())->values(),
        ];
    }

    /**
     * Resolve the physical containers relevant to this opportunity without creating
     * a second Opportunity-to-Container relationship. Before dispatch, relevance is
     * derived from assigned assets' housing stock level; after dispatch the canonical
     * container opportunity_id is used.
     *
     * @return list<array<string, mixed>>
     */
    protected function opportunityContainers(): array
    {
        return $this->opportunityContainersQuery()
            ->with([
                'product',
                'store',
                'serialisedItem.store',
                'activeItems.product',
                'activeItems.serialisedItem',
            ])
            ->withCount(['activeItems', 'children'])
            ->orderBy('name')
            ->get()
            ->filter(fn (Container $container): bool => Gate::allows('view', $container))
            ->map(function (Container $container): array {
                $compliance = app(ContainerManifestService::class)->compliance($container);
                $complianceSummary = collect($compliance->slots)
                    ->filter(fn ($slot): bool => $slot->status !== 'ok')
                    ->map(fn ($slot): string => $slot->status === 'unexpected'
                        ? __('Unexpected :actual', ['actual' => $slot->actual_quantity])
                        : __(ucfirst($slot->status).' — expected :expected, actual :actual', [
                            'expected' => $slot->expected_quantity,
                            'actual' => $slot->actual_quantity,
                        ]))
                    ->values()
                    ->all();

                return [
                    'uuid' => $container->uuid,
                    'name' => $container->name,
                    'barcode' => $container->barcode,
                    'status' => $container->status->label(),
                    'status_value' => $container->status->value,
                    'mode' => $container->availabilityMode()->label(),
                    'store' => data_get($container, 'store.name') ?? data_get($container, 'serialisedItem.store.name') ?? '—',
                    'contents_count' => (int) $container->active_items_count,
                    'children_count' => (int) $container->children_count,
                    'compliance_label' => $container->checkin_verification_required_at !== null
                        ? __('Verification required')
                        : ($complianceSummary === [] ? __('Complete') : implode(' · ', $complianceSummary)),
                    'compliance_attention' => $container->checkin_verification_required_at !== null || $complianceSummary !== [],
                    'show_url' => route('containers.show', $container),
                    'pack_url' => route('containers.pack', $container),
                    'can_pack' => Gate::allows('pack', $container),
                    'can_seal' => Gate::allows('seal', $container),
                    'can_dispatch' => Gate::allows('dispatch', $container),
                    'can_return' => Gate::allows('return', $container),
                    'can_verify' => Gate::allows('verifyCheckin', $container),
                    'is_bookable_kit' => $container->availabilityMode()->isBookableKit(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Builder<Container>
     */
    protected function opportunityContainersQuery(): Builder
    {
        $assignedHousingIds = OpportunityItemAsset::query()
            ->whereHas('item', fn ($query) => $query->where('opportunity_id', $this->opportunity->id))
            ->whereNotNull('container_stock_level_id')
            ->select('container_stock_level_id');

        return Container::query()
            ->active()
            ->where(function (Builder $query) use ($assignedHousingIds): void {
                $query
                    ->where('opportunity_id', $this->opportunity->id)
                    ->orWhereIn('serialised_item_id', $assignedHousingIds);
            });
    }

    protected function findOpportunityContainer(string $uuid): ?Container
    {
        $container = $this->opportunityContainersQuery()
            ->where('uuid', $uuid)
            ->with(['product', 'store', 'serialisedItem'])
            ->first();

        return $container !== null && Gate::allows('view', $container) ? $container : null;
    }

    /**
     * @param  list<array<string, mixed>>  $containers
     * @return array{total: int, dispatched: int, returned: int, ready: int, to_return: int}
     */
    protected function containerProgress(array $containers): array
    {
        $statuses = collect($containers)->pluck('status_value');
        $dispatched = $statuses->filter(fn (string $status): bool => in_array($status, ['dispatched', 'returned'], true))->count();
        $returned = $statuses->filter(fn (string $status): bool => $status === 'returned')->count();

        return [
            'total' => count($containers),
            'dispatched' => $dispatched,
            'returned' => $returned,
            'ready' => $statuses->filter(fn (string $status): bool => $status === 'sealed')->count(),
            'to_return' => $statuses->filter(fn (string $status): bool => $status === 'dispatched')->count(),
        ];
    }

    /**
     * Build the grouped asset table model: one group per product line, each carrying
     * the line's per-asset rows + its bulk Qty/Alloc/Out/In tallies. Every sub-tab
     * shares this one table below its controls.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildGroups(): array
    {
        $groups = [];

        foreach ($this->opportunity->items as $item) {
            $serialised = $this->itemIsSerialised($item);
            $required = (int) ceil((float) $item->quantity);

            $rows = [];

            foreach ($item->assets as $asset) {
                $lifecycleStatus = $asset->stockLevel?->asset?->lifecycleStatus();
                $isMissingOrLost = in_array($lifecycleStatus, [AssetLifecycleStatus::Missing, AssetLifecycleStatus::Lost], true);
                $statusLabel = match (true) {
                    $lifecycleStatus === AssetLifecycleStatus::Missing => AssetLifecycleStatus::Missing->label(),
                    $lifecycleStatus === AssetLifecycleStatus::Lost => AssetLifecycleStatus::Lost->label(),
                    $asset->status === AssetAssignmentStatus::CheckedIn && $asset->checked_at === null => __('Awaiting inspection'),
                    in_array($asset->status, [AssetAssignmentStatus::Dispatched, AssetAssignmentStatus::OnHire], true) => __('Still out'),
                    default => $asset->status->label(),
                };

                $rows[] = [
                    'id' => $asset->id,
                    'asset_number' => data_get($asset, 'stockLevel.asset_number')
                        ?? data_get($asset, 'stockLevel.serial_number')
                        ?? data_get($asset, 'stockLevel.item_name')
                        ?? '#'.$asset->id,
                    'status' => $asset->status,
                    'status_label' => $statusLabel,
                    'lifecycle_status' => $lifecycleStatus,
                    'lifecycle_label' => $lifecycleStatus?->label(),
                    'store_id' => $asset->stockLevel?->store_id,
                    'is_missing_or_lost' => $isMissingOrLost,
                    'container' => data_get($asset, 'container.asset_number')
                        ?? data_get($asset, 'container.item_name'),
                ];
            }

            $allocated = $item->assets->count();
            $prepared = $item->assets
                ->filter(fn (OpportunityItemAsset $asset): bool => $asset->status->value >= AssetAssignmentStatus::Prepared->value)
                ->count();
            $dispatched = $item->assets
                ->filter(fn (OpportunityItemAsset $asset): bool => $asset->status->value >= AssetAssignmentStatus::Dispatched->value)
                ->count();
            $returned = $item->assets
                ->filter(fn (OpportunityItemAsset $asset): bool => $asset->status->value >= AssetAssignmentStatus::CheckedIn->value)
                ->count();
            $inspectionFinalised = $item->assets
                ->filter(fn (OpportunityItemAsset $asset): bool => $asset->status === AssetAssignmentStatus::Finalised
                    && $asset->checked_at !== null)
                ->count();
            $inspectionPending = $item->assets
                ->filter(fn (OpportunityItemAsset $asset): bool => $asset->status === AssetAssignmentStatus::CheckedIn
                    && $asset->returned_at !== null
                    && $asset->checked_at === null)
                ->count();
            $missing = collect($rows)->where('lifecycle_status', AssetLifecycleStatus::Missing)->count();
            $lost = collect($rows)->where('lifecycle_status', AssetLifecycleStatus::Lost)->count();
            $stillOut = collect($rows)
                ->filter(fn (array $row): bool => in_array($row['status'], [AssetAssignmentStatus::Dispatched, AssetAssignmentStatus::OnHire], true)
                    && ! $row['is_missing_or_lost'])
                ->count();

            if ($serialised) {
                [$progressDone, $progressRequired, $progressLabel, $remainingLabel] = match ($this->subTab) {
                    'allocate' => [$allocated, $required, __('allocated'), __('to allocate')],
                    'prepare' => [$prepared, $required, __('prepared'), __('to prepare')],
                    'book_out' => [$dispatched, $required, __('dispatched'), __('to dispatch')],
                    'check_in' => [$returned, $dispatched, __('returned'), __('to check in')],
                    default => [$allocated, $required, __('allocated'), __('remaining')],
                };
            } else {
                $bulkDispatched = (float) $item->dispatched_quantity;
                $bulkReturned = (float) $item->returned_quantity;
                [$progressDone, $progressRequired, $progressLabel, $remainingLabel] = $this->subTab === 'check_in'
                    ? [$bulkReturned, $bulkDispatched, __('returned'), __('to return')]
                    : [$bulkDispatched, (float) $item->quantity, __('dispatched'), __('to dispatch')];
            }

            $remainingForAction = max(0, (float) $progressRequired - (float) $progressDone);

            $groups[] = [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->transaction_type->label(),
                'serialised' => $serialised,
                'rows' => $rows,
                'requested' => $required,
                'quantity' => $this->formatQuantity($item->quantity),
                'allocated' => $allocated,
                'prepared' => $prepared,
                'dispatched' => $this->formatQuantity($item->dispatched_quantity),
                'returned' => $this->formatQuantity($item->returned_quantity),
                'inspection_finalised' => $inspectionFinalised,
                'inspection_pending' => $inspectionPending,
                'still_out' => $stillOut,
                'missing' => $missing,
                'lost' => $lost,
                'remaining_dispatch' => $this->formatQuantity(max(0, (float) $item->quantity - (float) $item->dispatched_quantity)),
                'remaining_return' => $this->formatQuantity(max(0, (float) $item->dispatched_quantity - (float) $item->returned_quantity)),
                'progress_done' => $this->formatQuantity($progressDone),
                'progress_required' => $this->formatQuantity($progressRequired),
                'progress_label' => $progressLabel,
                'remaining_label' => $remainingLabel,
                'remaining_for_action' => $remainingForAction,
            ];
        }

        return $groups;
    }

    protected function formatQuantity(float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }

    public function render(): View
    {
        return view('project-opportunities::assets');
    }
}
