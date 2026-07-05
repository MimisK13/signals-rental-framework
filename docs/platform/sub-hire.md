---
title: Sub-Hire & Procurement
description: Purchase orders, virtual stock intakes, sub-hire shortage resolution, cost apportionment, and supplier returns.
---

## Overview

When owned stock cannot cover a shortage, **sub-hire** procures supply from suppliers. The loop connects shortage detection → purchase order → virtual stock intake → availability supply → cost apportionment → optional supplier return.

Event-sourced **purchase orders** and **virtual stock intakes** follow the same Verbs projection pattern as invoices and opportunities.

| Area | Route |
|------|-------|
| Purchase orders | `/purchase-orders` |
| Virtual stock intakes | `/virtual-stock` |
| Opportunity shortages | `/opportunities/{opportunity}/shortages` |

## Supplier costs

Products can carry **supplier cost** reference data — preferred supplier, unit cost, and lead time. Manage on the product detail page and via `POST /api/v1/products/{product}/supplier_costs/{id}/set_preferred`.

Supplier costs inform PO line pricing and margin reporting; **`costs.view`** gates cost field visibility on POs and intakes (not whole resources).

## Purchase orders

PO lifecycle:

| Status transition | Action |
|-------------------|--------|
| Draft | Create and edit lines |
| **Send** | Mark sent to supplier |
| **Receive** | Record goods received; triggers intake workflow |
| **Cancel** | Cancel open PO |

PO tax uses the **purchase axis** (distinct from sales tax on invoices). See [Purchase Orders API](/docs/api/purchase-orders).

## Virtual stock intakes

**Virtual stock intakes** represent supplier stock entering your availability pool as **signed supply** — the availability engine treats confirmed intake quantity as available for allocation without adding owned stock.

Intake lifecycle:

| Action | Purpose |
|--------|---------|
| **Confirm** | Confirm intake terms |
| **Receive** | Record physical receipt |
| **Apportion** | Allocate cost across opportunities |
| **Return** | Return stock to supplier |
| **Cancel** | Cancel intake |

See [Virtual Stock Intakes API](/docs/api/virtual-stock-intakes).

## Sub-hire shortage resolver

The **SubHireResolver** is the seventh shortage resolution path (alongside the six non-PO resolvers documented in [Shortages](/docs/platform/shortages)). It creates or consolidates purchase orders from shortage lines.

**Batch consolidation** groups shortages across opportunities into a single PO where supplier and dates align.

Flow: **PO receipt → intake confirm → shortage clear** — receiving a PO creates or updates the intake, which feeds availability and clears the originating shortage.

## Cost apportionment

When one intake serves multiple opportunities, **cost apportionment** distributes supplier cost using one of **five strategies** through the cost event stream. Date changes on opportunities trigger recalculation.

Override apportioned cost per opportunity: `PUT /api/v1/virtual_stock_intakes/{id}/cost_overrides` and `DELETE .../cost_overrides/{opportunity}`.

## Returns and P&L

**Return-to-supplier** workflow records stock leaving your pool. Intake **P&L** surfaces per-intake and per-opportunity profit impact when the viewer has cost visibility.

## Serialised virtual stock

Tracked sub-hire inventory can use **serialised virtual-stock assets** — individual serial numbers on intake lines for scan-based fulfilment.

## Using it in the app

Sub-hire UI spans product supplier costs, shortage resolution, purchase orders, virtual stock, and read-only cost apportionment on opportunities.

### Navigation

| Screen | Route | Nav location | Gate |
|--------|-------|--------------|------|
| Supplier Costs | `/products/{product}/supplier-costs` | Product tab | Tab visible with `costs.view` |
| Purchase Orders | `/purchase-orders` | Finance mega-menu + Purchasing sidebar | `purchase_orders.view` |
| Virtual Stock | `/virtual-stock` | Catalogue mega-menu + sidebar | `virtual_stock.view` |
| Opportunity Shortages | `/opportunities/{id}/shortages` | Opportunity tab | `shortages.view` + allocation visibility |
| Opportunity Costs | `/opportunities/{id}/costs` | Opportunity tab | `opportunities.view`; amounts `@costs` |

Virtual Stock is labelled **Sub-hire supply windows** in the header mega-menu. Finance-only users (`invoices.access` without catalogue access) do **not** see Virtual Stock links.

### Supplier Costs tab

On a product detail page, open **Supplier Costs** (tab requires **`costs.view`** and **`products.view`**).

**Table columns:** Supplier, Basis, Cost, SKU, Lead Time, MOQ, Store, Preferred

| Action | Permission |
|--------|------------|
| Add Supplier Cost | `products.supplier_costs` |
| Set preferred | `products.supplier_costs` |
| Edit / Delete | `products.supplier_costs` |

Without **`costs.view`**, the tab is hidden — not merely redacted.

### Shortages tab — sub-hire resolution

Visible for **Order** state or **Quotation + Reserved**. Tab badge shows unresolved shortage count.

**Sub-hire option cards** appear in **Detected Shortages** with resolver options sized to the shortfall. Cards can show **Preferred** (preferred supplier first), **Partial**, and **Auto** badges. **Resolve across jobs** offers batch consolidation into a single PO when supplier and dates align — **Apply batch**.

Lifecycle buttons on applied resolutions: Confirm, Start, Fulfill, Cancel, Fail.

Flow in UI: pick sub-hire option → draft PO created → receive PO → intake confirm → shortage clears (see PO and intake screens below).

### Purchase orders

**Index** (`/purchase-orders`) — Finance meta label; status chips; data table with custom views; columns Number, Type, Status, Supplier, Store, Total, Expected; **New Draft** when `purchase_orders.create`.

**Show page actions** (all writes also require **`costs.view`**):

| Action | Permission | Notes |
|--------|------------|-------|
| Edit | `purchase_orders.edit` | Draft only |
| Send | `purchase_orders.send` | Marks sent to supplier |
| Receive | `purchase_orders.receive` | Modal with per-line quantities (partial receipt supported) |
| Cancel | `purchase_orders.cancel` | Open PO |
| Delete | `purchase_orders.delete` | Draft only |

For **sub_hire** PO lines, **Create intake** links pre-fill `/virtual-stock/create?purchase_order_id=…&purchase_order_item_id=…` with supplier unit costs from the PO line.

**Virtual stock intakes** card lists linked intakes with **Return to supplier** when applicable.

### Virtual stock intakes

**Index** — status chips; columns Number, Product, Store, Status, Window, Qty, Total Cost (when `costs.view`); **New Intake**.

**Show page:**

| Action | Permission |
|--------|------------|
| Confirm | `virtual_stock.confirm` |
| Receive | `virtual_stock.receive` (partial/over-receipt handled in receive modal) |
| Return to supplier | `virtual_stock.return` |
| Cancel / Delete | Draft only |

Panels cover product, store, quantity, allocations, availability window, linked PO, per-source availability breakdown, and P&L/apportionment when **`costs.view`**.

**Late return** appears as an amber badge on the intake show page when the intake window has passed without return.

### Opportunity Costs tab

Read-only table: Description, Cost Type, Transaction Type, Amount (`@costs`), Qty, Optional. Lines linked to an intake show a **Intake {number}** link to `/virtual-stock/{id}` when the viewer has cost visibility.

Manual cost overrides and apportionment editing are API-driven — the Costs tab does not expose override controls.

### Permissions summary

| Permission | UI effect |
|------------|-----------|
| `costs.view` | Supplier Costs tab, PO/virtual-stock writes, cost columns |
| `products.supplier_costs` | CRUD supplier costs |
| `shortages.view` / `shortages.resolve` | Shortages tab view / resolution buttons |
| `purchase_orders.*` | PO list and lifecycle actions |
| `virtual_stock.*` | Intake list and lifecycle actions |

## Related documentation

- [Shortages](/docs/platform/shortages) — detection, gate, and resolver panel
- [Equipment Availability](/docs/platform/availability) — signed supply in availability calculations
- [Tax & Compliance](/docs/platform/tax) — purchase-axis tax
- [Purchase Orders API](/docs/api/purchase-orders)
- [Virtual Stock Intakes API](/docs/api/virtual-stock-intakes)
