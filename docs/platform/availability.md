---
title: Equipment Availability
description: Read equipment availability over time with a by-product-group grid, multi-store aggregation, and a per-product bookings drill-down with opportunity and asset rows.
---

## Overview

The **Equipment Availability** page shows, at a glance, how much of each product is free to book over a span of dates. It is a planning view over the rental [availability engine](/docs/api/availability): availability is the quantity of a product that is free at a store at a given time, where `available = total stock − total demanded`, and may go **negative** when demand exceeds stock — a shortage.

**Route:** `/availability`

The page lives under the **Job Planning** section of the navigation (the "Equipment Availability" link) and is gated on the `availability.view` permission. It is read-only — it reflects bookings made on opportunities elsewhere; it does not itself create demand.

The primary view is **By Product Group** — a products-by-days grid scoped to one catalogue group. Clicking a product name or cell opens the **Product Bookings** drill-down for that product. Store, date, group, and filter state are persisted in the URL so a link reopens exactly as shared.

## By Product Group

The **By Group** view is the landing view. It shows a **products-by-days grid** for the selected product group, making it the fastest way to check whether your lighting stock, audio stock, or any other category has room in a window.

The grid is tightened so **30 days fit on load** without horizontal scrolling on typical screens. Table text uses a softer sans-serif stack for long planning sessions.

### Controls

| Control | Effect |
|---------|--------|
| **Stores** | Multi-store tag-input (Signals tag-input). Empty = all in-default-query stores; availability and stock are **summed across** the selected stores via `AvailabilityService::getCalendarForStores` and `productTotalStockForStores`. Uses `PlanningStoreFilter`. |
| **Product Group** | The group whose products fill the rows. Required — select a group to populate the grid. |
| **Product type** | Filter rows to Rental or Sale products, or show all. |
| **Start date** | The first day of the window. Signals datepicker. |
| **Days** | The window length: 7, 14, 28, 30, or 60 days. Default 30. |
| **Shortages only** | Toggle — hide rows with no shortage cell in the window. |
| **Warnings only** | Toggle — hide rows with no warning (low-but-positive) cell in the window. |
| **Include quotes** | Toggle — whether provisional (quotation-stage) demand counts towards held (UI present; read-model filter pending). |

Previous/next buttons step the window by one full period length.

### Reading the grid

Each row is a product in the group; each column is a day. Cells are colour-coded by availability state:

| Colour | Meaning |
|--------|---------|
| Green | Available — comfortably in stock for this day. |
| Amber | Warning — low but still positive (2 units or fewer remaining). |
| Red | Shortage — demand exceeds stock (`available ≤ 0` with a shortage). |
| Cyan | Booked — every unit is committed but nothing is oversold (available = 0, no shortage). |

Each cell shows **`available (held)`** — the free quantity and, in parentheses, how much of the total stock is committed. Weekend columns are shaded.

Clicking a **product name** or cell opens **Product Bookings** for that product, carrying forward the current store selection, start date, and day count.

Rows are paginated (20 per page) for large groups.

### Layout chrome

- **Sticky filter bar** — page header and filters stay pinned while scrolling.
- **Sticky day headers** — column labels remain visible above the grid body.
- **Loading skeleton** — deferred first paint skeleton before availability data loads.

A **colour legend** below the grid explains cell states. **Delivery / In-Use / Collection** phase colours appear on the Product Bookings drill-down (see below), shared via `OpportunityPresentation`.

## Product Bookings

**Route:** `/availability/bookings/{product}/{from}/{days}`

The **Product Bookings** page (Current RMS "Product Bookings") focuses on a **single product** over a date window. It reuses the same grid chrome as the By Group view.

### Summary rows

At the top of the grid, three summary rows show per-day figures across the window:

| Row | Content |
|-----|---------|
| **Available** | Colour-coded free quantity per day (same green/amber/red/cyan rules as the group grid). |
| **Held** | Total stock minus available — how much is committed. |
| **Post-rent** | Pending check-in quantity (returned but not yet cleared). |

### Opportunity rows

Below the summaries, **one row per opportunity** (for bulk / non-asset demand) shows which days that booking claims stock. Cells use **Delivery / In-Use / Collection** phase colours from `OpportunityPresentation` so you can see not just *when* a unit is on hire but which part of the RMS lifecycle each day falls in. Row labels link to the opportunity detail page.

### Asset rows

For **serialised** products, **one row per asset** (asset number) follows the opportunity rows. Assets are **store-scoped** — only assets at the resolved store(s) appear. Each cell shows phase-coloured demand for that specific asset on that day.

When multiple stores are selected on the group grid, the bookings page inherits that selection and aggregates summary figures; asset rows list assets from all resolved stores.

### Navigating to bookings

From the By Group grid:

- Click the **product name** in the row label, or
- Click any **day cell** in that row.

The URL carries `product`, `from`, `days`, and optional `stores[]` query parameters.

## Shortages

Where committed demand exceeds stock, availability dips below zero. Use **Shortages only** on the group grid to focus on affected products, or open Product Bookings to see which opportunities and assets contribute to a short day. Shortages are detected and resolved per opportunity on the [Shortages panel](/docs/platform/shortages); the availability pages are where you spot them across the wider plan.

## Live Updates

The By Group page is **live**. It subscribes to each resolved store's `availability.store.{storeId}` broadcast channel, so when a booking, return, resolution, or stock movement changes the picture, the grid re-reads authoritative figures without a manual refresh.

## The Resolution Setting

Availability is bucketed into time slots whose granularity is set by the **availability resolution** setting — **daily**, **half-daily**, or **hourly**. The resolution determines how finely the underlying snapshots divide time, and dates are aligned to the slot boundary in the store's local timezone. Daily resolution suits most dry-hire operations; finer resolutions suit same-day turnarounds where intra-day timing matters.

> **Note:** Kit and composed products do not hold their own daily summary rows — their availability is derived at read time from their components. They therefore do not appear in the group grid; query a kit's components directly, or use the [availability API](/docs/api/availability) for a live read.

## Related

- [Availability API](/docs/api/availability) — point and range availability reads, calendar aggregation, Gantt demand decomposition, and the available-assets endpoint.
- [Job Planner](/docs/platform/planner) — overlap timeline of jobs across the same window.
- [Opportunities](/docs/platform/opportunities) — where demand is created.
- [Shortages](/docs/platform/shortages) — detecting and resolving shortfalls.
