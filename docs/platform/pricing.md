---
title: Pricing & Discounts
description: Four-stage pricing pipeline — price categories, discount rules, surcharges, bundle pricing, deal price, and rounding.
---

## Overview

Signals applies pricing through a **four-stage pipeline** after base rates are calculated by the [Rate Engine](/docs/platform/rate-definitions). Stages run in order; each stage receives the output of the previous one. Opportunity totals recalculate when lines, dates, discount categories, surcharges, or deal price change.

Admin configuration lives under **Admin → Pricing** (requires pricing management permissions).

| Stage | Admin route | What it does |
|-------|-------------|--------------|
| **0 — Base rates** | [Rate Definitions](/docs/platform/rate-definitions) | Composable rate definitions and calculation strategies |
| **1 — Price categories** | `/admin/settings/pricing/price-categories` | Tiered price categories threaded through the rate engine |
| **2 — Discount rules** | `/admin/settings/pricing/discount-categories`, discount rules | JSONB criteria engine with auto-apply |
| **3 — Surcharges** | `/admin/settings/pricing/surcharge-groups`, surcharges | Optional surcharge groups with per-surcharge tax classes |
| **4 — Volume & bundles** | `/admin/settings/pricing/bundle-rules` | Quantity-break and bundle pricing |

## Stage 1 — Price categories

**Price categories** assign tiered pricing (e.g. retail vs trade) to products and opportunities. Categories integrate with the rate engine so line calculations pick up the active category tier.

Configure categories at `/admin/settings/pricing/price-categories`. See [Price Categories API](/docs/api/price-categories).

## Stage 2 — Discount rules

**Discount categories** group related rules. **Discount rules** evaluate JSONB criteria via `CriteriaMatcher` and can **auto-apply** when an opportunity matches.

Discount rule changes are event-sourced on Verbs streams. Apply a category manually from an opportunity via **Apply discount category** (UI) or `POST /api/v1/opportunities/{id}/apply_discount_category`.

See [Discount Categories API](/docs/api/discount-categories) and [Discount Rules API](/docs/api/discount-rules).

## Stage 3 — Surcharges

**Surcharge groups** bundle optional surcharges (delivery, weekend hire, etc.). Operators toggle groups on an opportunity; individual line surcharges can be overridden.

Each surcharge carries its own **tax class**; surcharge line snapshots survive source deletion so historical invoices stay accurate.

Toggle a group: `POST /api/v1/opportunities/{id}/toggle_surcharge_group`. Override a line surcharge: `POST /api/v1/opportunities/{id}/items/{item}/override_surcharge`.

See [Surcharges API](/docs/api/surcharges) and [Surcharge Groups API](/docs/api/surcharge-groups).

## Stage 4 — Volume and bundle pricing

**Bundle rules** implement quantity-break and bundle pricing — for example "hire 5+, get 10% off" or fixed bundle totals. Evaluated after surcharges in the pipeline.

Configure at `/admin/settings/pricing/bundle-rules`.

## Deal price

**Deal price** sets a manual headline total on an opportunity, bypassing calculated line totals. Two modes:

| Mode | Behaviour |
|------|-----------|
| **Flat headline** | Sets a fixed opportunity total |
| **Proportional** | Distributes the headline across lines proportionally |

Deal price is **mutually exclusive with price lock** — you cannot lock rates and override the total simultaneously. Use **Recalculate** to refresh lines from the pipeline, or **Clear deal price** to remove the override.

API: `POST /api/v1/opportunities/{id}/deal_price` and `DELETE /api/v1/opportunities/{id}/deal_price`.

## Rounding strategy

`PricingSettings` controls rounding: **half-up** (default) or **banker's rounding** (round half to even). Applied at the final minor-unit boundary for line and document totals.

## Checkout interaction

When an opportunity **checks out** to an invoice, the active pricing snapshot (lines, surcharges, deal price, tax) is frozen onto the invoice. Post-checkout opportunity recalculation does not alter issued documents.

## Related documentation

- [Opportunities](/docs/platform/opportunities) — line editor, Recalculate, pricing lock
- [Invoicing](/docs/platform/invoicing) — checkout and frozen snapshots
- [Tax & Compliance](/docs/platform/tax) — tax on surcharges and lines
