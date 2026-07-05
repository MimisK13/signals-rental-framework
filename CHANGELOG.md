# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.6.0] - 2026-07-05

Phase 4 release: quote-to-cash billing, EU/UK tax engine, document template engine, notification & communication engine, sub-hire procurement loop, and serialised container lifecycle.

### Added

- **Licensing** — Signals relicensed under **AGPL-3.0-only** (v0.5.2+); `NOTICE` and `CLA.md` shipped; [Licensing docs page](/docs/getting-started/licensing)
- **Event-sourced financial aggregates** — invoices, payments, credit notes, purchase orders, and virtual stock intakes as Verbs event streams with projected read models
- **Quote-to-cash** — checkout with frozen tax snapshot; invoice issue & lifecycle; payments, refunds, and balance; deposit/pro-forma and partial-quantity invoicing; credit notes; invoice/credit-note PDFs; billing emails; Finance UI (Invoices, Credit Notes, Payments); `Idempotency-Key` on financial POSTs
- **Four-stage pricing pipeline** — price categories + tiers, discount rules with criteria engine, surcharges with per-surcharge tax classes, quantity-break/volume + bundle pricing, deal price with Recalculate; half-up and banker's rounding
- **EU/UK tax engine** — tax zones, four buyer types, zone-aware resolution (reverse charge, zero-rated, exempt, outside-scope), async VIES validation with revalidation, finance settings / tax-point policy, tax audit trail
- **Document template engine** — per-store sandboxed Blade templates; DomPDF with repeating headers/footers; field-registry browser; CodeMirror 6 editor with live preview, version diff/restore; concurrency-safe numbering; public token sharing (PIN, expiry, QR); frozen regeneration from snapshots
- **Notification & communication engine** — channel provider registry; three-layer resolution pipeline; event→notification wiring; merge-field templates; customer comms with opt-out; real-time bell; communication log
- **Sub-hire & procurement** — supplier costs; event-sourced POs; virtual stock intakes; sub-hire shortage resolver with batch consolidation; PO receipt → intake → shortage clear; five cost-apportionment strategies; supplier returns + P&L; serialised virtual-stock assets; field-level cost visibility
- **Serialised container lifecycle** — config/templates; pack/unpack; seal/unseal + compliance manifest; manual dissolve; dissolve-on-dispatch; kit dispatch/return; repack-on-return; warehouse UI
- **APIs** — REST surfaces for invoices, credit notes, payments, purchase orders, virtual stock, documents, templates, sequences, pricing, tax, and communications; extended webhook catalogue

See [docs/changelog/0.6.0.md](docs/changelog/0.6.0.md) for full detail.

### Changed

- **Owner bypass** no longer skips state-dependent policy rules
- **Admin sidebar** always shows all sections
- **Flat-deal checkout** carries headline totals through to issued invoices

### Fixed

- Invoice overdue tab, credit-note partial editor, notification bell refresh, invoice payment PK collision, demo seeder tax/template provisioning, and UAT sweep items (bell badge, reverse-charge demo, checklist detections)

## [0.5.1] - 2026-06-28

### Added

- **Product Bookings** page at `/availability/bookings/{product}/{from}/{days}` — per-product demand grid with summary rows (available, held, post-rent), one row per opportunity (bulk demand), and one row per serialised asset; Delivery / In-Use / Collection day-phase colours on every row; links back to the parent opportunity
- **`App\Services\Opportunities\OpportunityPresentation`** — single source of truth for opportunity phase band colours (Delivery / In-Use / Collection), status swatches, icons, labels, customer Collecting/Returning badges, and legend assembly; shared by the Job Planner and availability booking grids
- **`App\Services\Planning\PlanningStoreFilter`** — multi-store name↔id resolution for planning views (tag-input store filter, default = all in-default-query stores)
- **`AvailabilityService` cross-store aggregation** — `getCalendarForStores`, `productTotalStockForStores`, and `getGanttForStores` sum availability and stock across selected stores for the By Group grid and Product Bookings drill-down
- **Demo seeders** — `PlannerDemoSeeder` and `AvailabilityDemoSeeder` (including asset allocations) for local planning/availability demos
- **API Console** — summary at `/api` plus Explorer, Request Log, Usage, and Webhooks areas under a dedicated API sidebar section
- **Weighted metering** — price weights 0–4 on API endpoints (`#[PriceWeight]`), outbound webhook events (`WebhookEventRegistry`), and inbound sources (DB column); weight 0 = logged but not metered
- **Request logging & rollups** — `request_logs` detail table, `usage_daily` aggregates, Redis-buffered `UsageRecorder`, `api:flush-logs` and `api:prune-logs` scheduled commands
- **Inbound webhook receiver** — `POST /webhooks/receive/{slug}` with HMAC signature verification and `webhook_received` metering
- **Outbound webhook metering** — `DeliverWebhook` increments `webhook_sent` rollups on first attempt
- **REST meta endpoints** — `GET /api/v1/usage`, `/api/v1/usage/export`, `/api/v1/request_logs`, `/api/v1/endpoints` (all weight 0)
- **Registries** — `AbilityRegistry`, extended `WebhookEventRegistry`, `ApiEndpointCatalogue`
- **Docs landing page** at `/docs` (card grid per section) and **⌘K search** across titles, descriptions, headings, and body text
- **Docs placeholder pages** for Workflows and Plugin SDK under Development

### Changed

- **Docs navigation restructured** around the app's areas — Getting Started, CRM, Job Planning, Resources, Finance, Operations, Development (API reference nested within), Changelog at the bottom; collapsible sidebar sections with per-level indentation; page URLs unchanged
- **SSO and Magic-Link docs merged into Authentication & Settings** with permanent redirects from the old URLs

See [docs/changelog/0.5.1.md](docs/changelog/0.5.1.md) for full detail.

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

[0.6.0]: https://github.com/signals-rental/signals-framework/compare/v0.5.1...v0.6.0
[0.5.1]: https://github.com/signals-rental/signals-framework/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/signals-rental/signals-framework/releases/tag/v0.5.0
