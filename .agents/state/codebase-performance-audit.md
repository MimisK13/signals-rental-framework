# Codebase performance audit

## Objective

Read-only, evidence-backed audit of the full application for performance risks and
high-value refactor boundaries. Do not modify production code.

## Workstreams

- Database queries, Eloquent access patterns, algorithms, and backend hot paths.
- Livewire, Blade, Alpine/JavaScript payloads, rendering, and request behavior.
- Jobs, events, observers, cache invalidation, recalculation, and operational scaling.

## Current evidence

- Experimental quotation heavy fixture: 228 rows, 216 product lines.
- Current warm full snapshot after targeted refactors: approximately 184-230 ms.
- Current prepared availability pass: approximately 10-11 ms.
- Current tree builder: approximately 62-92 ms.

## Verified findings

- Experimental batch field mutations can trigger full opportunity repricing once
  per mutation; repricing repeats surcharge, rate, tax, product, and item-write
  work per line.
- The production quotation editor still uses the older full-tree sync protocol,
  serial field requests, and public catalogue state.
- The default local worker listens only to `default`; 230 queued availability
  jobs currently remain pending on the named `availability` queue, representing
  only 14 unique product/store pairs.
- Database cache contains 21,608 expired rows out of 21,616 and no scheduled
  cache-table cleanup was found.
- All pages receive a single 707,291-byte raw JS bundle containing both quotation
  editors and CodeMirror; the built gzip size is approximately 233 KB.
- Asset selection rerenders the full workspace per checkbox and performs one
  container compliance traversal per container.
- Container manifest methods repeatedly query child/parent containers during
  recursive traversals.
- Restricted-user dashboard measurement repeated identical store-access queries
  ten times (32 total queries, about 112 ms elapsed on the current small DB).
- Global search performs up to 11 sequential leading-wildcard searches; only the
  products search has a discovered trigram index.
- Product search performs per-result availability work and the public catalogue
  read is unbounded.

## Decisions

- Report static call-graph findings as confirmed behavior, but do not claim
  production latency without representative profiling.
- Rank queue correctness/coalescing, quotation batch repricing, and production
  editor protocol as the first refactor tranche.
- Recommend browser traces before virtualization or Tailwind source changes.

## Follow-up completed

- Removed `laravel/horizon` and its provider/configuration/tests at the user's
  request because Horizon's process-control requirements block Windows usage.
- The development queue listener now consumes every named application queue.
- Replacement queue configuration tests pass; Composer validation and audit pass.

## Next action

Deliver a ranked read-only report with measurement gates and a proposed refactor
sequence. No production code or database data was changed.

## Maintainability assessment (2026-10-28)

- Expanded review across backend, frontend and test organization. Inventory:
  1,717 app files and 1,113 test files; this was a hotspot/call-path assessment,
  not a line-by-line certification of every file.
- Highest-value boundaries: one pricing pass per mutation batch, prepared pricing
  facts, batch shortage detection, indexed prepared demand reads, reusable loaded
  container graph, separate availability read surfaces.
- Other concrete candidates: split Opportunity API endpoints by subresource;
  explicit purchase-order receipt plan/execution; shared container packing writer;
  per-operation documentation manifest index; asset-workspace reader/scanner
  resolver; explicit document preview contract.
- Test quality: coverage-batch tests with unconditional assertions should become
  domain-owned observable behavior tests when touched. Preserve unique coverage.
- Current worker configuration already covers named queues after Horizon removal;
  historical backlog/cache measurements above are not current-state measurements.
- Existing actions/readers, coherent relationship-heavy models and immutable
  registries should be retained. Avoid project-wide architecture replacement.
