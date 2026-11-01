# Pricing batch

## Objective

Start pricing batch refactoring without corrupting Verbs events, intermediate
line audits, manual prices, or action guards.

## Decision

Transparent full repricing deferral is unsafe: price projection is produced only
by the calculator; legacy audits capture intermediate totals; quantity changes
can fire category discount events and dates trigger virtual-stock cost changes.
Implement parent-rollup batching as the first coherent tranche. A single full
pricing pass requires an explicit batch event/audit contract.

## Implemented

- Scoped OpportunityTotalsBatch wraps events and final rollup atomically, uses
  CommitsVerbsEvents rollback cleanup, and clears its scope in finally.
- Calculator defers only parent rollup for the selected opportunity.
- Experimental field batch uses the service and refreshes final response totals.
- Added tests for one rollup and intermediate audits, rollback/recovery, empty
  batch, sequential parity, pricing freeze guards and replay.

## Verification / next action

Final targeted batch/editor tests: 19 passed, 121 assertions. This includes
sequential parity, rollback/recovery, replay and unchanged audit snapshots.
Pint and targeted PHPStan passed. Wider pricing/lifecycle run: 38 passed and four
bundle tests failed inserting fixture items with nonexistent revenue_group_id
(1/7), before pricing; those fixtures were not changed.

Review found notification mappings can read intermediate parent totals. Added
conservative fallback to immediate rollups whenever any declared batch audit
action has a notification mapping, including registered plugin mappings. The
fallback is covered by an integration test observing the parent total.

Next: report this explicitly as parent-rollup batching. Full one-pass pricing
remains separate work requiring a persisted batch event/audit contract.

## Continuation: explicit price/discount event

- Added ApplyItemPricingBatch / ItemPricingBatchApplied for batches of 2–100
  price/discount-only fields. Event records final before/after pricing in one
  batch audit; legacy per-field notification maps trigger fallback.
- Added optional per-item manual override map to calculator, preserving null
  resets, zero and unchanged overrides from Verbs state.
- Mixed quantity/date/name batches and single edits retain legacy behavior and
  the parent-rollup optimization above. They have additional side effects.
- 7 new integration tests passed (37 assertions): one pricing write/event,
  invalid batch rollback, cross-opportunity ownership, replay, rate reset/zero/
  unchanged overrides, notification fallback and calculator failure/retry.
- Targeted PHPStan passed. Combined editor/pricing tests are running. Added
  bulk state snapshot preload to avoid per-ID snapshot reads; rerun that path.
- New batch audit shape: old/new {items:[{item_id,unit_price,discount_percent,
  is_discount_manual,total}],charge_total}. No schema changes.

## Final continuation verification

- Cold-state review caught unrelated state loading. Replaced it with
  OpportunityItemManualPriceReader: two bulk reads verify snapshots against
  latest committed event IDs; missing/outdated snapshots reconstitute normally.
  Only changed states belong to the new batch event; untouched manual prices
  are historical facts carried in its payload.
- Final combined tests: 29 passed / 166 assertions. Cold test covers ten items,
  at most two snapshot reads and only one changed state-event association.
- Missing and outdated snapshot recovery, full replay, rollback/retry, legacy
  notification fallback and manual override handling all pass.
- Targeted PHPStan, Pint and diff checks passed. No migrations/seeds/commits.
- User asked whether Verbs benefits the app. Explain event-history/replay value
  against mirrored state/projection/audit complexity; no authorization to remove
  Verbs has been given. Pause further expansion pending user's direction.

Only SQLite memory tests are used; do not run migrations, seeds or profiling
mutations on the development database. Preserve pre-existing dirty work.
