---
title: Job Planner
description: An overlap timeline of quotes and orders across a configurable planning window, with lane-packed job cards coloured by workflow status and Delivery / In-Use / Collection phase bands.
---

## Overview

The **Job Planner** is a visual overlap timeline of all active quotes and orders plotted across a date window, so you can see at a glance when jobs run, how they overlap, and which customer is taking delivery or returning equipment. It is a planning and coordination view — it does not itself create or modify jobs; those are managed on the [Opportunities](/docs/platform/opportunities) pages.

**Route:** `/planner`

The planner lives under the **Job Planning** section of the main navigation and is gated on the `opportunities.access` permission.

## The Timeline

Jobs are packed into horizontal **lanes** by the EventLaneAllocator — concurrent jobs that genuinely overlap sit side-by-side rather than hiding each other. The grid is a horizontal day-column layout that fills its panel edge-to-edge.

Each job appears as a **card** spanning its hire window. The card background colour reflects the opportunity's current workflow status (driven by `OpportunityStatus` via the shared `OpportunityPresentation` service):

| Colour | Status |
|--------|--------|
| Grey | Draft / Open |
| Amber | Quotation / Provisional |
| Violet | Quotation / Reserved |
| Blue | Order / Active, Dispatched, or On Hire |
| Green | Order / Returned, Checked, or Complete |
| Red | Lost, Dead, or Cancelled |

A **colour key / legend** below the filter bar identifies each status swatch plus the Delivery / In-Use / Collection phase bands and Customer Collecting / Returning badges.

### Job cards

Each card shows:

- **Number — subject** as the headline
- **Date/time range** for the hire window
- **Venue address** when set on the opportunity
- **Status line** with per-status colour and label
- **Opportunity tags** when present
- **Customer Collecting** and **Customer Returning** badges when those flags are set

The whole card links to the opportunity detail page.

### Phase bands

Where an opportunity carries RMS lifecycle dates, a **status row** beneath the card body is subdivided into up to three **phase bands** snapped to day columns:

- **Delivery** — the delivery window (`deliver_starts_at` to `deliver_ends_at`).
- **In Use** — the show/event window (`show_starts_at` to `show_ends_at`), or the full hire span when no show dates are set.
- **Collection** — the collection window (`collect_starts_at` to `collect_ends_at`).

Phase band colours are shared with the [Equipment Availability](/docs/platform/availability) booking grids via `OpportunityPresentation`.

### Heatmap and weekends

A **per-day job-count row** above the lanes uses colour intensity to show load (more jobs = deeper tint). **Weekend columns** are shaded to help read multi-week windows at a glance.

## Filters & Controls

The sticky filter bar exposes the following controls; all settings are reflected in the URL so a planner link re-opens exactly as shared:

| Control | Effect |
|---------|--------|
| **Stores** | Multi-store tag-input filter (empty = all in-default-query stores). Uses `PlanningStoreFilter`. |
| **Start date** | The first day of the planning window. Signals datepicker; defaults to today. |
| **Previous / Today / Next** | Shift the window backwards or forwards by one full period. |
| **Zoom** | Window width: 1 Week (7 days), 2 Weeks (14 days), or 1 Month (30 days). |
| **Presets** | Next 30 days, This Month, or Next Month (overrides zoom when active). |
| **Orders / Quotes** | Toggle switches to include or exclude order-state and quotation-state jobs. Both on by default. |
| **Search** | Free-text filter across job subject, opportunity number, and member name. |

The default view is **1 Month** with **both orders and quotes** included.

## Layout chrome

- **Sticky control bar** — page header and filter panel stay pinned while scrolling the grid.
- **Sticky day headers** — day labels and the job-count heatmap row remain visible above the lane area.
- **Loading skeleton** — a deferred grid skeleton paints on first load before jobs are fetched (`wire:init`).

## Counts

Three badge counters above the planner show the total **Jobs**, **Orders**, and **Quotes** visible in the current window after filters are applied.

## Related

- [Opportunities](/docs/platform/opportunities) — create and manage the jobs shown on the planner.
- [Equipment Availability](/docs/platform/availability) — see equipment stock across the same window and drill into per-product bookings.
