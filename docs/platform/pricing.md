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

## Using it in the app

Admin pricing configuration lives under **Admin → Pricing** (admin sidebar group `pricing`). Links require **`pricing.view`**; create, edit, and delete buttons require **`pricing.manage`**. Rate Definitions sit in the same group but use **`rates.view`**.

| Admin screen | Route |
|--------------|-------|
| Discount Categories | `/admin/settings/pricing/discount-categories` |
| Discount category + rules form | `/admin/settings/pricing/discount-categories/create` or `…/{id}/edit` |
| Price Categories | `/admin/settings/pricing/price-categories` |
| Price category + tiers form | `/admin/settings/pricing/price-categories/create` or `…/{id}/edit` |
| Bundle Rules | `/admin/settings/pricing/bundle-rules` |
| Surcharge Groups | `/admin/settings/pricing/surcharge-groups` |
| Surcharge group form | `/admin/settings/pricing/surcharge-groups/create` or `…/{id}/edit` |

There is no standalone **Discount Rules** index — rules are edited inline on each discount category form.

### Discount categories and rules

The **Discount Categories** index lists name, description, and active status. **Add Discount Category** opens the create form.

On the category form, the **Discount Rules** section holds repeatable rule blocks. Each rule defines:

- Discount %, quantity scope (per line / aggregate product / aggregate group), min/max quantity, sort order
- Valid from / valid to (datetime)
- Product groups, tags (comma-separated, all required), products, and transaction types (Rental, Sale, Service)

Empty criteria means the rule matches all products. Rules use AND logic across criteria fields.

### Price categories and tiers

**Price Categories** assign tiered multipliers. The form's **Tier Multipliers** section lists tier number, label, and multiplier (create defaults to one tier: "Day 1" at `1.0000`).

### Surcharge groups

**Surcharge Groups** index shows name, Default vs Optional badge, and status. The group form exposes:

- **Default group (auto-enabled on new opportunities)** checkbox
- Repeatable **Surcharges** — name, percent value, fixed value (minor units), tax class, and **Default (applies when group is active)**

Surcharge criteria fields (product groups, tags, valid dates, and so on) exist in the backend sync layer but are **not** rendered on the admin form yet.

### Bundle rules

**Bundle Rules** configure fixed bundle price or percentage reduction modes with a required product set. Bundle Rules appear in the admin sidebar but not on the admin landing overview cards.

### Member assignment

On the member create/edit form (**Tax & Pricing** section, organisations and venues), assign:

| Field | Purpose |
|-------|---------|
| Price Category | Active price list for the member |
| Price Category Tier | Tier within that category |
| Discount Category | Discount rule set to evaluate on their opportunities |

These feed the pricing pipeline when an opportunity is priced for that member.

### Opportunity line-item editor

Pricing effects are visible on the opportunity **Overview** tab (`/opportunities/{id}`), which embeds the line-item editor. Editing requires **`opportunities.edit`** and an open opportunity; **`opportunities.unlock_rates`** controls lock/unlock price.

**Toolbar actions:**

| Control | Behaviour |
|---------|-----------|
| **Recalculate** | Re-runs the pricing pipeline (`resetPrices` and `resetDiscounts` stay false in the UI) |
| **Deal price** (currency icon) | Opens modal — Flat headline override or Proportional line distribution; Clear / Set deal price |
| Lock icon (footer/header) | Opens confirm to lock or unlock rates (blocked while deal price is active) |

**Line presentation:**

- Auto-applied discounts appear in the **Disc %** column
- Manually overridden discounts show a **Manual** badge; row menu offers **Reset to category discount**
- Charge breakdown popover (hover charge total) shows rental charge and **Surcharge amount**
- When deal price is active, the footer shows the deal total alongside the calculated charge total

**Row edit modal** exposes unit price override, discount %, dates, description, and substitute product.

**Price lock:** when locked, inline price/discount/qty edits and line removal are disabled; banner reads "Pricing is frozen — rates and tax are locked" unless a deal price headline is shown instead.

**Not in Web UI yet:** toggling surcharge groups on an opportunity (`toggle_surcharge_group` exists on the API only). Applying a discount category manually from the UI is also API-only — assignment flows through the member's discount category and auto-apply rules.

### Permissions summary

| Permission | UI effect |
|------------|-----------|
| `pricing.view` | Admin Pricing sidebar links |
| `pricing.manage` | Create / edit / delete on pricing admin pages |
| `rates.view` | Rate Definitions admin link |
| `opportunities.edit` | Line-item toolbar and inline edits |
| `opportunities.unlock_rates` | Lock / Unlock price in Actions menu and editor |

## Related documentation

- [Opportunities](/docs/platform/opportunities) — line editor, Recalculate, pricing lock
- [Invoicing](/docs/platform/invoicing) — checkout and frozen snapshots
- [Tax & Compliance](/docs/platform/tax) — tax on surcharges and lines
