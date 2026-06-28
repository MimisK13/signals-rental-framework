# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.1] - 2026-06-28

### Added

- **Product Bookings** page at `/availability/bookings/{product}/{from}/{days}` — per-product demand grid with summary rows (available, held, post-rent), one row per opportunity (bulk demand), and one row per serialised asset; Delivery / In-Use / Collection day-phase colours on every row; links back to the parent opportunity
- **`App\Services\Opportunities\OpportunityPresentation`** — single source of truth for opportunity phase band colours (Delivery / In-Use / Collection), status swatches, icons, labels, customer Collecting/Returning badges, and legend assembly; shared by the Job Planner and availability booking grids
- **`App\Services\Planning\PlanningStoreFilter`** — multi-store name↔id resolution for planning views (tag-input store filter, default = all in-default-query stores)
- **`AvailabilityService` cross-store aggregation** — `getCalendarForStores`, `productTotalStockForStores`, and `getGanttForStores` sum availability and stock across selected stores for the By Group grid and Product Bookings drill-down
- **Demo seeders** — `PlannerDemoSeeder` and `AvailabilityDemoSeeder` (including asset allocations) for local planning/availability demos

### Changed

- **Job Planner** — overlap-only timeline (Gantt mode and layout toggle removed); horizontal day-column grid filling its panel; per-day job-count heatmap row; weekend shading; sticky control bar plus day/count headers; lane-packed job cards showing number — subject, date/time range, venue address, status line, opportunity tags, and Customer Collecting/Returning badges; whole card links to the opportunity; card colours and labels driven by `OpportunityStatus`; Delivery / In-Use / Collection phase bands snapped to day columns; default view = next 1 month with both orders and quotes included
- **Equipment Availability (By Group)** — tightened grid so 30 days fit on load; softer sans-serif table font; multi-store selection via Signals tag-input (default all stores, summed availability); Signals datepicker; filter toggles for Shortages/Warnings/Include quotes (switches); loading skeletons; filter row aligned with the Job Planner
- **Planning filter rows** — unified store tag-input, datepicker, and toggle styling across planner and availability pages

### Fixed

- **Claude Code Review GitHub Action** — restored `anthropics/claude-code-action@v1` after a beta→v1 regression (PR #43)
- **Coverage scripts** — purge stale `.php` coverage dumps before each `composer test:coverage` run so merged reports stay accurate

## [0.5.0] - 2026-06-27

Phase 3 release: event-sourced opportunity lifecycle, demand-based availability engine, shortage detection and resolution, quote versioning, asset allocate/dispatch/return, and the first Job Planning UI shell.

### Added

- **Opportunities** — two-axis state/status model with event-sourced writes and read projections; full list, detail, create/edit, and state-transition actions; local-first line-item editor with nestable groups, deal price, and per-line availability chips; asset allocation, dispatch, and return for serialised and bulk products
- **Availability engine** — demand-based `available = stock − demanded` with PostgreSQL range types, snapshots, and daily summaries; Equipment Availability page (By Group, Calendar, and Timeline/Gantt modes at launch); live Reverb updates
- **Shortages** — live detection, Block/Warn/Allow confirmation gate, six resolvers, and resolution lifecycle
- **Quote versioning** — revisions and alternatives with diff and activation
- **Job Planning navigation** — Opportunities, Equipment Availability, and Job Planner under a shared section
- **Job Planner (initial)** — Gantt and overlap timeline of quotes and orders with status colouring
- **Dashboard** — pipeline/shortage widget and New Opportunity quick action
- **API** — `/api/v1` surfaces for opportunities, availability, shortages, and containers

See [docs/changelog/0.5.0.md](docs/changelog/0.5.0.md) and the GitHub Release for full detail.

[0.5.1]: https://github.com/signals-rental/signals-framework/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/signals-rental/signals-framework/releases/tag/v0.5.0
