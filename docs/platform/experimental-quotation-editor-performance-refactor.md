# Experimental Quotation Editor Performance Refactor

## Status

Implemented through the batch-read, compact-sync, single-flight, catalogue-state,
drag-hit-testing, compact full-snapshot, availability-loop, and row-projection phases on
2026-10-25. Virtualization remains deferred until browser profiling justifies it.

The editor should remain an Experimental presentation and workflow surface over the
canonical Opportunity actions, models, availability, planning, pricing, and shortage
services. The proposal does not introduce a second quotation domain or bypass existing
authorization and domain events.

## Measured baseline

Measurements were taken against the existing PostgreSQL heavy quotation fixture:

- 12 sections;
- 216 product lines;
- 228 total rows;
- 33 unique product/store/period contexts.

| Operation | Queries | Database time | Elapsed time | Serialized output |
| --- | ---: | ---: | ---: | ---: |
| Component mount and `tree()` | 118 | 144.93 ms | 901.96 ms | 379,995-byte tree |
| Initial Livewire render | 121 | 136.56 ms | 987.48 ms | 692,959-byte HTML |
| Non-stale `pullTree()` | 112 | 102.38 ms | 798.18 ms | 380,177-byte response |

PHP application bootstrap time is excluded from the component measurements.

### Verified result

| Operation | Before | After |
| --- | ---: | ---: |
| Full tree queries | 108 | 11-12 |
| Full tree JSON | 379,995 bytes | 236,082 bytes |
| Warm full tree elapsed | ~830 ms median | ~200 ms median |
| Initial Livewire render queries | 121 | 18 |
| Initial HTML | 692,959 bytes | 668,828 bytes |
| Same-revision sync queries | 112 | 4 |
| Same-revision sync response | 380,177 bytes | 148 bytes |
| Same-revision sync elapsed | ~798 ms | ~12 ms |
| Queued cell-edit requests | one per field plus totals | one atomic batch |

Before the compact full-snapshot phase, the full rich tree remained 379,995 bytes and
forced snapshots remained approximately 0.8-1.0 seconds on the measurement machine.
Query scaling was bounded; domain computation and row construction dominated full
snapshots.

### Full-snapshot profile and refactor

The warmed 228-row profile isolated the remaining application cost:

| Stage | Median |
| --- | ---: |
| Shared item read | 28.92 ms |
| Prepared availability inputs | 72.30 ms |
| Canonical tree builder | 440.77 ms |
| Planning reader | 238.15 ms |
| Planning merge | 10.72 ms |
| JSON encoding | 1.34 ms |

The tree builder repeated approximately 131.61 ms of availability work already performed
for planning. The editor now builds planning first and passes its canonical
`available_quantity` values into `OpportunityLineItemsTreeBuilder`, which preserves the
existing shortage/status decisions without executing the second availability pass.

`QuotationEditorTreeProjector` now exposes an explicit editor contract. It removes ten
unused or client-derived top-level fields and reduces each planning object from 23 fields
to the nine fields read by Alpine. Exact field-attribution against the captured heavy tree
reduced the measured PostgreSQL heavy tree from 379,995 to 236,082 bytes, a 37.9%
reduction while retaining dates, URLs, conflict inputs, and the charge-breakdown popover.
A representative 250-line unit fixture enforces a full-snapshot budget below 250 KB.

After restoring the 228-row PostgreSQL fixture, six full snapshots measured 15 queries /
530.57 ms cold and 10 queries warm. Warm elapsed samples were 470.99, 469.75, 1,072.17,
655.77, and 636.42 ms, for a 636.42 ms median. Database time was 11.33-13.84 ms warm.
Compared with the earlier ~830 ms median stage profile, the refactor reduced median
application elapsed by approximately 23%, with the expected variance from local PHP
execution. The deterministic contract, payload budget, canonical availability equality,
query-path tests, and browser build are covered.

### Availability and row-projection CPU pass

A later stocked-fixture profile showed that the remaining warm full snapshot took
approximately 394-459 ms. The dominant planning sub-stage was
`AvailabilityService::availableForItems()` at approximately 115-117 ms for 216 lines.
Each line independently rescanned the complete Demand collection for every time slot,
even when lines shared the same product, store, and window.

The availability pass now sums total demand once per shared slot and subtracts only the
matching line's self-demand. A regression test covers distinct self-demand windows so
the exclusion cannot leak into slots where that line has no demand. The same prepared
216-line input now measures 10-11 ms.

After that change, the canonical tree builder was the next limit at approximately
173-201 ms. It repeatedly formatted identical money values, generated identical product
and availability URLs, recalculated identical chargeable periods, and scanned all paths
to determine `has_children` for every row. Those values are now memoized only for the
duration of one tree build, and child lookup is prepared in one linear pass.

On the same 228-row fixture, seven staged snapshots produced warm totals of 184.11,
194.57, 197.78, 200.32, 215.88, and 229.87 ms (289.05 ms cold). The warmed tree-builder
stage measured 62.08-105.78 ms, while the payload remained 236,626 bytes. The normal
same-revision sync remains the preferred path and does not build this snapshot.

Before the batch-reader refactor, the dominant query pattern was 99 queries for 33
unique contexts:

1. one stock aggregate per product/store;
2. one standard Demand read per product/store/period;
3. one virtual-stock-intake Demand read per product/store/period.

That implementation avoided a query per quotation row, but still performed three
queries per unique planning context. The prepared-input reader now replaces that query
growth with bounded batch reads.

### Current implementation touchpoints

- [`QuotationEditor::tree()`](../../app/Livewire/Experimental/QuotationEditor.php)
  coordinates the tree and planning reads.
- [`PreparedStockInputs`](../../app/Data/Opportunities/PreparedStockInputs.php) and
  [`PreparedStandardDemandInputs`](../../app/Data/Opportunities/PreparedStandardDemandInputs.php)
  are the existing explicit read boundaries to preserve.
- [`OpportunityItemPlanningReader`](../../app/Services/Planning/OpportunityItemPlanningReader.php)
  currently performs the separate virtual-stock-intake reads.
- [`LineItemTreeReconciler`](../../app/Services/Opportunities/LineItemTreeReconciler.php)
  defines the fields needed for local/server conflict reconciliation.
- [`editor.js`](../../resources/js/experimental/quotation-editor/editor.js) owns the
  local-first synchronization workflow.
- [`tree-drag.js`](../../resources/js/experimental/quotation-editor/tree-drag.js) owns
  pointer hit-testing and placeholder rendering.
- [`HeavyQuotationSeeder`](../../database/seeders/HeavyQuotationSeeder.php) creates the
  manual 12-section/216-line measurement fixture.

## Pricing batch boundary

Price/discount-only batches of 2–100 field changes use `ApplyItemPricingBatch`
and the persisted `ItemPricingBatchApplied` event. Repeated field changes are
folded to their final value, then all lines are priced in one pass using their
event-sourced manual overrides, including explicit null resets and zero prices.
One `opportunity.item_pricing_batch_applied` audit records before/after item
pricing and parent totals; the old individual price/discount audits are not emitted.

Single edits, mixed batches (quantity/date/name), and batches with notification
mappings for the legacy audit actions retain the existing per-field events.
Quantity and date batching remains separate work because those events also
update automatic discounts, demands, shortages and cost apportionment.

Field batches now defer parent totals rollup through the request-scoped
`OpportunityTotalsBatch`. The final rollup runs once inside the same transaction
as the item events. Failure resets both the totals scope and Verbs bookkeeping.
The batch response refreshes the opportunity after that rollup.

On the legacy path, line repricing remains immediate: audits capture intermediate line totals,
price-bound checks read the updated price projection, and date mutations can
trigger cost apportionment. This is parent-rollup batching, not a single full
repricing pass. The price/discount-only path above uses its own persisted batch
event and audit semantics.

Callbacks inside the totals batch must not consume intermediate parent totals.
Standalone mutations retain their immediate totals behavior.
When one of the declared batch audit actions has a notification mapping, the
batch retains immediate parent rollups as well: mapping conditions can inspect
intermediate parent totals. Callers must declare every audit action they emit.

## Goals

1. Make the editor read path use a bounded number of queries rather than a number
   proportional to unique planning contexts.
2. Avoid returning or uploading a full rich tree when the client and server revisions
   already agree.
3. Make one logical mutation result in one synchronization response.
4. Prevent overlapping pulls from duplicating expensive reads or applying responses
   out of order.
5. Keep drag interaction within the browser's frame budget for quotations of at least
   the current 228-row fixture size.
6. Add regression budgets before changing the architecture further.

## Non-goals

- Replacing Livewire or Alpine.
- Moving canonical pricing, availability, planning, or shortage rules into JavaScript.
- Creating nested Livewire components for individual rows or cells.
- Promoting Experimental code into the production Opportunity editor.
- Adding virtualization before browser profiling demonstrates that it is necessary.
- Changing persisted tree, revision, conflict, or domain-event semantics.

## Recommended order

```text
1. Batch stock and Demand reads
        |
        v
2. Compact the synchronization contract
        |
        v
3. Make mutation responses renderless and self-contained
        |
        v
4. Coalesce concurrent pulls
        |
        v
5. Optimize pointer drag geometry
        |
        v
6. Re-measure before considering virtualization
```

Each phase should be separately testable and reversible.

## 1. Batch stock and Demand reads

### Current shape

`QuotationEditor::prepareLineItemsRead()` deduplicates contexts correctly, but then
executes each unique context separately:

```php
foreach ($contexts as $context) {
    $stockByKey[$key] = $pipeline->totalStock(
        $preparedProducts->required($context->productId),
        $context->storeId,
    );
}

foreach ($contexts as $context) {
    $demandByKey[$key] = Demand::query()
        ->where('product_id', $context->productId)
        ->where('store_id', $context->storeId)
        ->active()
        ->overlapping($context->from, $context->to)
        ->get();
}
```

`OpportunityItemPlanningReader::externalSupplyQuantities()` then performs another
Demand query for every unique context.

### Proposed boundary

Keep the existing prepared-input objects, but populate them through focused batch
readers. The component should coordinate the read; it should not own query construction.

Illustrative signatures:

```php
final class StockContextReader
{
    /**
     * @param  list<StockReadContext>  $contexts
     */
    public function read(array $contexts): PreparedStockInputs
    {
        // One grouped query, keyed by product and store.
    }
}

final class DemandContextReader
{
    /**
     * @param  list<DemandReadContext>  $contexts
     */
    public function read(array $contexts): PreparedStandardDemandInputs
    {
        // One bounded read, followed by deterministic context partitioning.
    }
}
```

The editor orchestration becomes small and explicit:

```php
$stockInputs = $stockContextReader->read(
    StockReadContext::fromOpportunityItemContexts($contexts),
);

$demandInputs = $demandContextReader->read(
    DemandReadContext::fromOpportunityItemContexts($contexts),
);

$planning = $planningReader->forOpportunity(
    $opportunity,
    $items,
    $contexts,
    $products,
    $stockInputs,
    $demandInputs,
);
```

### Stock query example

The exact stock expression should remain owned by the existing stock service. Its batch
form should use the same business filters and aggregate by the complete lookup key:

```php
$stockByKey = StockLevel::query()
    ->whereIn('product_id', $productIds)
    ->whereIn('store_id', $storeIds)
    ->selectRaw('product_id, store_id, SUM(quantity_held) AS total_stock')
    ->groupBy('product_id', 'store_id')
    ->get()
    ->mapWithKeys(static fn (StockLevel $stock): array => [
        PreparedStockInputs::key($stock->product_id, $stock->store_id)
            => (int) $stock->total_stock,
    ])
    ->all();
```

This snippet is a shape example, not a replacement for
`RecalculationPipeline::totalStock()`. Before implementation, move the canonical
filters and stock calculation into one shared batch-capable boundary so the batch and
single-product results cannot diverge.

### Demand query example

Contexts can have different time windows. A safe first implementation can fetch the
bounded candidate set once and partition it in PHP using the same overlap rule:

```php
$from = collect($contexts)->min(
    static fn (DemandReadContext $context) => $context->from,
);
$to = collect($contexts)->max(
    static fn (DemandReadContext $context) => $context->to,
);

$candidates = Demand::query()
    ->whereIn('product_id', $productIds)
    ->whereIn('store_id', $storeIds)
    ->active()
    ->overlapping($from, $to)
    ->get();

$demandsByKey = [];

foreach ($contexts as $context) {
    $key = PreparedStandardDemandInputs::key(
        $context->productId,
        $context->storeId,
        $context->from,
        $context->to,
    );

    $demandsByKey[$key] = $candidates
        ->where('product_id', $context->productId)
        ->where('store_id', $context->storeId)
        ->filter(static fn (Demand $demand): bool =>
            $demand->bufferedStartsAt()->lt($context->to)
            && $demand->bufferedEndsAt()->gt($context->from)
        )
        ->values();
}
```

The real implementation should index candidates by `product_id|store_id` before the
context loop so partitioning is not contexts multiplied by all candidates. It must also
reuse the same prepared Demand collections for standard availability and virtual stock
intake totals. If a quotation can span a very wide range, group or chunk the candidate
query by time window after checking PostgreSQL query plans and representative row counts.

### Expected result

For the measured fixture, the 99 context queries should become a small bounded set:

- one stock aggregate query;
- one standard/virtual Demand candidate query, or a small fixed number of chunked
  queries;
- the existing fixed opportunity, product, allocation, and revision queries.

The first acceptance target should be fewer than 20 queries for the full 228-row tree,
subject to confirming that all canonical behavior remains identical.

## 2. Compact the synchronization contract

### Current shape

`pullTree()` always calculates and returns a rich server tree. The browser normally
sends `this.rows` back as `localRows`, even though conflict detection only compares:

- `id`;
- `path` or ordering information;
- `quantity`;
- `unit_price`;
- `discount_percent`;
- `name`;
- pending local IDs.

The measured tree is approximately 380 KB. Sending a similarly rich local tree and
then returning another full tree can approach 760 KB before the Livewire message and
snapshot envelope.

### Proposed response types

Use two response shapes:

```php
/**
 * @phpstan-type EditorSyncAck array{
 *     changed: false,
 *     revision: string,
 *     charge_total: int,
 *     deal_total: int|null,
 *     has_deal_price: bool,
 *     cache_token: string
 * }
 *
 * @phpstan-type EditorTreeSnapshot array{
 *     changed: true,
 *     stale: bool,
 *     revision: string,
 *     tree: list<array<string, mixed>>,
 *     conflicts: array<int, string>,
 *     charge_total: int,
 *     deal_total: int|null,
 *     has_deal_price: bool,
 *     cache_token: string,
 *     collapsed_section_ids: list<int>
 * }
 */
```

An unchanged revision should return an acknowledgement without building the tree:

```php
#[Renderless]
public function syncEditor(
    int $baseRevision,
    array $localProjection = [],
    array $pendingLocalIds = [],
    bool $forceTree = false,
): array {
    $this->refreshEditorContext();

    $serverRevision = $this->treeRevision();
    $stale = $serverRevision > $baseRevision;

    if (! $forceTree && ! $stale && $pendingLocalIds === []) {
        return [
            'changed' => false,
            'revision' => (string) $serverRevision,
            'cache_token' => $this->editorCacheToken(),
            ...$this->totalsPayload(),
        ];
    }

    $serverTree = $this->tree();
    $result = $this->treeReconciler->reconcile(
        $localProjection,
        $serverTree,
        $pendingLocalIds,
    );

    return [
        'changed' => true,
        'stale' => $stale,
        'revision' => (string) $serverRevision,
        'tree' => $result['rows'],
        'conflicts' => $result['conflicts'],
        'cache_token' => $this->editorCacheToken(),
        'collapsed_section_ids' => $this->collapsedSectionIds,
        ...$this->totalsPayload(),
    ];
}
```

Availability broadcasts can change planning output without changing the structural tree
revision. Those callers must use `forceTree: true`, or planning must receive its own
revision/token. The optimization must not treat the current structural revision as an
availability revision.

### Minimal client projection

```js
localProjection() {
    const pendingIds = new Set(this.pendingLocalIds());

    return this.rows
        .filter((row) => row.id < 0 || pendingIds.has(Number(row.id)))
        .map((row) => ({
            id: Number(row.id),
            path: row.path,
            depth: Number(row.depth),
            quantity: row.quantity,
            unit_price: row.unit_price,
            discount_percent: row.discount_percent,
            name: row.name,
        }));
}
```

Only locally owned or pending rows need to be uploaded for reconciliation. A full local
tree should be reserved for structural persistence, where order and depth are the actual
command payload.

## 3. Return one self-contained mutation result

Livewire 4.4 action promises resolve with the PHP method's return value. `#[Renderless]`
skips view rendering. Both fit this Alpine-owned `wire:ignore` editor.

### Current request chain

```text
mutation
    -> component render may evaluate tree
    -> line-items-mutation-done event
    -> pullTree
    -> totalsSnapshot
```

Quick-add paths can additionally request the revision before the pull.

### Proposed action result

```php
/**
 * @return array{
 *     revision: string,
 *     charge_total: int,
 *     deal_total: int|null,
 *     has_deal_price: bool,
 *     changed_row: array<string, mixed>|null,
 *     requires_tree: bool
 * }
 */
#[Renderless]
public function updateField(int $itemId, string $field, mixed $value): array
{
    $this->guardEditable();

    $item = $this->findItem($itemId);
    $this->fieldUpdater->update($item, $field, $value);
    $this->refreshOpportunity();

    return [
        'revision' => (string) $this->treeRevision(),
        'changed_row' => $this->rowProjection($item->fresh()),
        'requires_tree' => false,
        ...$this->totalsPayload(),
    ];
}
```

The client consumes that one result:

```js
const result = await this.$wire.updateField(itemId, field, value);

this.applyChangedRow(result.changed_row);
this.baseRevision = normalizeRevision(result.revision);
this.applyServerTotals(result);

if (result.requires_tree) {
    await this.requestPull({ forceTree: true, reason: 'mutation' });
}
```

Structural operations can return `requires_tree: true`. Simple field mutations should
return a changed-row projection and totals without forcing a full tree read.

For Livewire actions used only by JavaScript, `#[Json]` is also available in the
installed Livewire version and skips rendering automatically. The implementation should
choose one convention consistently. Keeping `#[Renderless]` initially is the smaller
change because the component already uses it.

### Batch field changes

The client currently awaits queued field mutations sequentially. Coalesce repeated
changes by row and field, then commit them through one endpoint:

```js
compactFieldMutations(mutations) {
    const latest = new Map();

    for (const mutation of mutations) {
        latest.set(`${mutation.id}:${mutation.field}`, mutation);
    }

    return [...latest.values()];
}
```

```php
#[Renderless]
public function applyFieldMutations(array $mutations): array
{
    $validated = $this->validateMutationBatch($mutations);

    return DB::transaction(function () use ($validated): array {
        foreach ($validated as $mutation) {
            $this->fieldUpdater->update(
                $this->findItem($mutation['id']),
                $mutation['field'],
                $mutation['value'],
            );
        }

        $this->refreshOpportunity();

        return [
            'revision' => (string) $this->treeRevision(),
            ...$this->totalsPayload(),
        ];
    });
}
```

The concrete implementation must preserve the canonical action calls and their domain
events. The batch endpoint should coordinate them, not replace their business rules.
Before batching, skip actions whose normalized incoming value is unchanged.

## 4. Coalesce pulls and reject obsolete work

Echo, BroadcastChannel, lifecycle events, mutation completion, and structural flushes
can all request a pull. `_shouldDeferPull()` handles some queue state, but it does not
serialize pulls already in flight.

A small single-flight coordinator is sufficient:

```js
async requestPull(options = {}) {
    this._pendingPull = {
        ...this._pendingPull,
        ...options,
        forceTree: Boolean(this._pendingPull?.forceTree || options.forceTree),
    };

    if (this._pullPromise) {
        return this._pullPromise;
    }

    this._pullPromise = (async () => {
        while (this._pendingPull && !this._destroyed) {
            const next = this._pendingPull;
            this._pendingPull = null;

            const response = await this.$wire.syncEditor(
                this.baseRevision,
                this.localProjection(),
                this.pendingLocalIds(),
                Boolean(next.forceTree),
            );

            if (!this._destroyed) {
                this.applySyncResponse(response);
            }
        }
    })().finally(() => {
        this._pullPromise = null;
    });

    return this._pullPromise;
}
```

This serializes server reads and merges multiple reasons that arrive during a pull into
one subsequent pull. `destroy()` should set `_destroyed`, clear `_pendingPull`, cancel
timers and idle callbacks, and invalidate outstanding product-search sequences.

Only one layer should own each realtime subscription. The editor currently has both a
Livewire Echo listener and a direct JavaScript Echo listener for availability, and both
a Blade window listener and `Livewire.on()` for lifecycle changes. Keep one subscription
per event and route it through `requestPull()`.

## 5. Keep the catalogue out of Livewire snapshot state

The complete catalogue is currently a public Livewire property and is also copied into
the frozen JavaScript seed. Public properties are part of the Livewire snapshot, so the
catalogue remains attached to later messages even though Alpine already owns its index.

Remove the public property and fetch a lightweight index once:

```php
use Livewire\Attributes\Json;

#[Json]
public function catalogueIndex(): array
{
    Gate::authorize('products.read');

    return collect(app(ProductSearchService::class)
        ->catalogueIndex($this->opportunity->store_id))
        ->map(static fn (array $product): array => [
            'id' => $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
        ])
        ->all();
}
```

```js
async loadCatalogue() {
    const catalogue = await this.$wire.catalogueIndex();
    this.productSearch.addAll(catalogue);
}
```

The example wraps the current array result in a collection. A dedicated lightweight
service method would avoid first building and then discarding rates, accessories, and
signed image URLs. Those details should be fetched only for displayed or selected
results. If catalogue size becomes large, cache the store-scoped lightweight index with
explicit invalidation for product identity changes.

## 6. Optimize drag hit-testing before adding virtualization

The current pointer-move handler scans rendered rows twice, reads layout geometry for
each row, and removes/recreates the placeholder. That work occurs for every raw pointer
event.

### Cache geometry at drag start

```js
const measureRows = () => Array.from(
    tbody.querySelectorAll('tr.lf-row'),
)
    .filter((row) => !drag.blockIds.has(Number(row.dataset.id)))
    .map((row, index) => {
        const rect = row.getBoundingClientRect();

        return {
            id: Number(row.dataset.id),
            index,
            top: rect.top,
            midpoint: rect.top + rect.height / 2,
            bottom: rect.bottom,
        };
    });
```

### Process at most once per animation frame

```js
let pointerFrame = null;
let latestPointerEvent = null;

const onDragMove = (event) => {
    latestPointerEvent = event;

    if (pointerFrame !== null) {
        return;
    }

    pointerFrame = requestAnimationFrame(() => {
        pointerFrame = null;
        processDragMove(latestPointerEvent, measuredRows);
    });
};
```

Reuse one placeholder element and move it only when the resolved target changes. Refresh
measurements when the grid scrolls or its geometry changes. With sorted row midpoints,
the insertion point can be found by binary search rather than a full scan.

Do not introduce row virtualization until a browser trace still shows material render,
input, or frame latency after these changes. Virtualization complicates keyboard
navigation, focus retention, drag targets, row height, and the editor's flat-tree
semantics.

## 7. Performance regression coverage

The current Experimental tests cover rendering, ownership, adding products and text
items, and drag markup. They do not enforce query growth, payload size, or request count.

### Query growth test

Prefer testing bounded growth rather than a fragile exact query count:

```php
it('keeps quotation tree reads bounded across unique planning contexts', function (): void {
    $small = HeavyQuotationScenario::create(uniqueContexts: 5);
    $large = HeavyQuotationScenario::create(uniqueContexts: 40);

    $smallQueries = countQueries(
        fn () => editorTreeFor($small),
    );
    $largeQueries = countQueries(
        fn () => editorTreeFor($large),
    );

    expect($largeQueries - $smallQueries)->toBeLessThanOrEqual(6);
});
```

The scenario helper should create its own records through factories and expose intent
through named parameters. It should not depend on `DatabaseSeeder` or the manually
seeded heavy quotation.

### Payload budget test

```php
it('keeps a 250-line editor snapshot within its agreed payload budget', function (): void {
    $opportunity = HeavyQuotationScenario::create(lineCount: 250);
    $tree = editorTreeFor($opportunity);
    $bytes = strlen(json_encode($tree, JSON_THROW_ON_ERROR));

    expect($tree)->toHaveCount(262)
        ->and($bytes)->toBeLessThan(250_000);
});
```

The exact budget should be agreed after the tree projection is defined. The test should
protect an intentional contract rather than merely encode today's 380 KB payload.

### Request-contract tests

Add feature coverage proving that:

- an unchanged revision returns `changed: false` without building a tree;
- an availability refresh forces planning refresh even when structure is unchanged;
- a simple field mutation returns revision and totals without rendering the Blade tree;
- stale structural state returns the full snapshot and preserves pending local rows;
- duplicate realtime notifications are coalesced by the browser coordinator.

The final item needs a JavaScript unit test or browser-level measurement. The project
does not currently include a browser testing dependency, so do not add one solely for
this proposal without explicit approval.

## Proposed acceptance budgets

These are initial targets for the existing heavy fixture, not permanent universal
limits:

| Metric | Baseline | Initial target |
| --- | ---: | ---: |
| Full tree queries | 108 | fewer than 20 |
| Initial render elapsed | ~987 ms | below 350 ms locally |
| Forced full-tree pull elapsed | ~798 ms | below 300 ms locally |
| Unchanged sync response | full ~380 KB tree | below 10 KB |
| Full-tree JSON | ~380 KB | below 250 KB after projection |
| Simple field mutation | multiple serial requests | one request |
| Pointer processing | every raw event, two scans | at most once per frame |

Time budgets are development-machine indicators and should not be asserted in ordinary
CI. Query-growth and payload budgets are deterministic enough for regression tests.

## Rollout plan

### Phase A: establish budgets

- Add unique-context query-growth coverage.
- Add serialized tree and unchanged-sync payload measurements.
- Capture a browser performance trace for initial render, editing, and drag on the heavy
  fixture.

### Phase B: batch reads

- Introduce focused stock and Demand batch readers.
- Reuse prepared Demand data in availability and planning.
- Confirm output equality against current services.
- Re-run PostgreSQL query plans and the heavy fixture measurements.

### Phase C: compact synchronization

- Add acknowledgement and full-snapshot response shapes.
- Upload only pending-row conflict projections.
- Return tree data only for stale, structural, or forced availability refreshes.
- Remove the separate revision and totals requests after a full pull.

### Phase D: mutation and realtime cleanup

- Make Alpine-owned mutation endpoints consistently renderless.
- Return revision, totals, and changed-row data directly.
- Batch compatible field mutations while preserving canonical actions and events.
- Keep one listener per realtime event and coalesce pull requests.
- Complete timer, idle callback, and async teardown on `wire:navigate`.

### Phase E: browser hot path

- RAF-throttle pointer movement.
- Cache drag geometry and reuse the placeholder.
- Cache derived group totals and structural flags only if profiling still shows them as
  meaningful contributors.
- Decide on virtualization only from the new trace.

## Decision summary

The refactor is justified by measured server cost. The first implementation should not
split the editor merely because its files are large. It should instead change the two
boundaries responsible for most cost:

1. context reads become batch operations;
2. editor synchronization becomes revision-aware and compact.

Once those boundaries are in place, mutation and drag improvements are small,
independent optimizations. This sequence preserves the current local-first workflow and
canonical domain behavior while providing measurable checkpoints after every phase.
