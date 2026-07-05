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

## Related documentation

- [Shortages](/docs/platform/shortages) — detection, gate, and resolver panel
- [Equipment Availability](/docs/platform/availability) — signed supply in availability calculations
- [Tax & Compliance](/docs/platform/tax) — purchase-axis tax
- [Purchase Orders API](/docs/api/purchase-orders)
- [Virtual Stock Intakes API](/docs/api/virtual-stock-intakes)
