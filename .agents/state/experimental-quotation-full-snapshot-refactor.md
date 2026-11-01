# Experimental quotation full-snapshot refactor

## Objective

Reduce the CPU, serialization, and browser cost of forced full-tree snapshots while
preserving editor behavior, planning correctness, and the compact acknowledgement path.

## Current baseline

- Heavy fixture: 228 rows / 33 unique contexts.
- Forced tree: 10-12 warm queries, approximately 184-230 ms elapsed on the current
  228-row fixture.
- Rich tree payload: 379,995 bytes.
- Initial HTML: 668,828 bytes.
- Ordinary same-revision sync is already optimized to 4 queries / 148 bytes / ~12 ms.

## Completed

- Profiled builder, planning, merge, and JSON stages; builder + planning represented
  approximately 86% of snapshot construction time.
- Inventoried the complete Blade/Alpine row contract.
- Added `QuotationEditorTreeProjector` with an explicit top-level and nine-field planning
  projection.
- Reused planning availability in `OpportunityLineItemsTreeBuilder`, eliminating its
  duplicate availability pass while preserving canonical status output.
- Added a representative 250-line payload budget below 250 KB.
- Added a test-runner safety boundary: PHPUnit ignores development config cache and
  `Tests\TestCase` aborts before reset traits unless it boots on SQLite `:memory:`.
- Verified 35 targeted PHP tests / 226 assertions, projector budget tests, both JavaScript
  runners, Pint, and the Vite production build.
- Restored the PostgreSQL heavy fixture: 12 groups / 216 products / 228 total rows.
- Post-refactor result: 236,082-byte tree (-37.9%), 10 warm queries, 636.42 ms warm median
  across five samples versus the earlier ~830 ms stage-profile median.
- Aggregated shared-slot demand once and applied each line's self-demand as a small
  subtraction. Prepared availability for 216 lines fell from ~115-117 ms to ~10-11 ms.
- Memoized money displays, product/availability URLs, and chargeable-day results within
  a single tree build, and replaced per-row path scans with a prepared child-path set.
  The builder fell from ~173-201 ms to ~62-92 ms in representative warm samples.
- Latest staged full-snapshot warm median is approximately 200 ms; payload is 236,626
  bytes and output contracts remain covered by targeted tests.

## Incident note

Before the test safety boundary was added, Laravel loaded the development config cache and
`RefreshDatabase` reset the PostgreSQL `signals` database. No manual migration command was
run. The database was found with the current schema and zero business rows. Further tests
were stopped until the fail-fast SQLite boundary was implemented and verified.

## Constraints

- Do not change canonical availability, planning, pricing, or shortage decisions.
- Do not add virtualization without browser evidence.
- Preserve pending-row reconciliation and forced availability refresh behavior.
- Keep the current dirty refactor files isolated from the commits already pushed.

## Next action

Run a real browser performance trace of initial hydration, scroll, editing, and drag on
the 228-row fixture. Only then decide whether the next limit is Livewire hydration/DOM
work, lazy detail fields, or row virtualization.
