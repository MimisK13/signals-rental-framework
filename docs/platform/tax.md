---
title: Tax & Compliance
description: EU/UK tax zones, buyer types, zone-aware resolution, VIES validation, and finance settings.
---

## Overview

Phase 4 extends the Phase 2 tax engine with **zone-aware resolution** for EU/UK compliance. Tax is resolved per document context — opportunities, invoices, credit notes, and purchase orders — through `ZoneAwareTaxResolver` and `DocumentTaxBreakdown` extraction.

The legacy rule matrix (organisation class + product class → rate) remains for reference data; see [Tax Classes](/docs/platform/tax-classes). Zone rules take precedence for financial documents in configured territories.

## Tax zones

**Tax zones** group countries and regions with zone-specific rules. Each zone defines how buyer type and product tax category combine to produce a treatment.

| Admin route | Purpose |
|-------------|---------|
| `/admin/settings/tax/zones` | List zones |
| `/admin/settings/tax/zones/create` | Create zone |
| `/admin/settings/tax/zones/{taxZone}/edit` | Edit zone rules |

See [Tax Zones API](/docs/api/tax-zones).

## Buyer types

Four **buyer types** drive zone resolution:

| Type | Typical use |
|------|-------------|
| **Business** | B2B with VAT number |
| **Individual** | B2C consumer |
| **Government** | Public sector |
| **Charity** | Registered charity |

Buyer type is set on the member and consumed at document resolution time. Fresh installs seed reachable zone rules for each type.

## Resolution outcomes

`ZoneAwareTaxResolver` returns treatments including:

| Treatment | Description |
|-----------|-------------|
| **Standard rated** | Normal VAT on net amount |
| **Reverse charge** | Customer accounts for VAT; legal text on documents |
| **Zero-rated** | 0% rated supply |
| **Exempt** | Exempt from VAT |
| **Outside scope** | Not subject to VAT |

Reverse charge eligibility can depend on **VIES VAT validation** (see below). The reverse-charge display label is configurable under Finance settings.

## Product tax category

Products carry a **tax category** dimension end-to-end on line items and documents. Surcharges also reference per-surcharge tax classes. See [Tax Classes](/docs/platform/tax-classes) for the underlying class definitions.

## VIES VAT validation

For EU B2B reverse charge, Signals validates VAT numbers asynchronously against **VIES**:

- Validation runs after VAT number entry or manual confirmation
- Results feed reverse-charge eligibility on the member
- **30-day revalidation** with a scheduled expiry check job
- Webhook: **`member.vat_validated`** fires after VIES or manual confirmation

## Finance settings

**Route:** `/admin/settings/finance`

| Setting area | Purpose |
|--------------|---------|
| **Tax-point policy** | When tax is determined (e.g. invoice date vs payment date) |
| **Reverse-charge label** | Legal text displayed on reverse-charge documents |
| Finance toggles | Consumed by checkout and document rendering |

Tax configuration changes are recorded in the **tax configuration audit trail**.

## Document axes

| Document | Tax axis |
|----------|----------|
| Opportunity | Live resolution on lines; frozen at checkout |
| Invoice | Frozen snapshot from checkout |
| Credit note | Negated mirror of invoice tax |
| Purchase order | Purchase-axis tax for sub-hire procurement |

## Using it in the app

Tax admin lives under **Admin → Tax** (sidebar group `tax`, gated on **`tax-classes.view`** for the Tax nav link). Mutations on tax classes, rates, rules, zones, and categories go through action classes that require **`tax-classes.manage`**.

| Screen | Route |
|--------|-------|
| Finance settings | `/admin/settings/finance` |
| Tax Zones | `/admin/settings/tax/zones` |
| Tax Categories | `/admin/settings/tax/categories` |
| Product Tax Classes | `/admin/settings/tax/product-tax-classes` |
| Organisation Tax Classes | `/admin/settings/tax/organisation-tax-classes` |
| Tax Rates | `/admin/settings/tax/rates` |
| Tax Rules | `/admin/settings/tax/rules` |

Tax Zones and Tax Categories are in the admin sidebar but not on the admin landing overview cards (those cards list Finance, legacy classes, rates, and rules only).

### Finance settings

**Route:** `/admin/settings/finance`

| Section | Fields |
|---------|--------|
| **Tax Point** | Tax point date — Supply date or Invoice date |
| **Pricing Defaults** | New opportunities default to prices include tax; Show tax breakdown on documents |
| **VAT Validation** | VAT validation validity (days) — how long a VIES/HMRC check remains trusted |
| **Reverse Charge Display** | Reverse charge label — text shown on tax breakdowns and documents when reverse charge applies |

Click **Save Changes** to persist. There is no dedicated tax-configuration audit UI — changes surface in the general [Action Log](/docs/platform/admin-panel) when auditable events fire.

### Tax zones, categories, and rules

**Tax Zones** — list shows Name, Slug, Countries (member-country count), Priority, Status. Form fields: Name, Slug, Description, Priority, Active.

**Tax Categories** — list shows Name, Slug, Type, Default, Status. Form: Name, Slug, Type (Standard / Reduced / Zero rated / Exempt), Description, Default category, Active.

**Tax Rules** index combines legacy **Class** rules and **Zone** rules in one table (Type, Name/Mapping, Zones, Buyer, Result, Priority, Status).

- **Add Class Rule** — organisation tax class + product tax class → tax rate, priority, active
- **Add Zone Rule** — seller zone, buyer zone, optional buyer type, requires validated tax ID, tax category, result type (Standard / Reverse charge / Zero rated / Exempt / Outside scope), legal text, priority, active

Reverse-charge **legal text** on zone rules feeds document and totals rendering; the configurable display label comes from Finance settings.

### Member tax fields

On the member form (organisations and venues), the **Account** section shows **Tax Number** with validation badges:

| Badge | Meaning |
|-------|---------|
| Pending validation | VAT number entered, check not yet complete |
| Valid | Passed VIES/HMRC validation |
| Invalid | Validation failed |

**Re-check VAT** (`members.edit`) re-queues validation.

The **Tax & Pricing** section also exposes **Sale Tax Class** and **Purchase Tax Class** (legacy class axis). Fields for **tax zone**, **buyer type**, and **tax exempt** exist on the member model but are **not** on the member form UI yet — zone resolution uses member country and organisation data at runtime.

### Store seller zone

Stores are managed at `/admin/settings/stores` (Admin → Setup). The form covers name and address only — there is **no Tax Zone picker** on the store modal. Seller zone for documents is resolved from each store's country/`tax_zone_id` at runtime via `TaxZoneResolver`.

### Reverse charge in documents

When zone resolution returns reverse charge, totals and generated documents show the **reverse charge label** from Finance settings plus any **legal text** configured on the zone rule. No separate reverse-charge editor exists outside those admin screens.

### Permissions summary

| Permission | UI effect |
|------------|-----------|
| `tax-classes.view` | Tax admin sidebar link |
| `tax-classes.manage` | Create / edit / delete tax classes, rates, rules, zones, categories |
| `members.edit` | Re-check VAT on member form |

## Related documentation

- [Multi-Currency & Tax](/docs/platform/multi-currency) — currencies, exchange rates, base engine
- [Tax Classes](/docs/platform/tax-classes) — product/organisation classes and rule matrix
- [Invoicing](/docs/platform/invoicing) — frozen tax at checkout
- [Sub-Hire & Procurement](/docs/platform/sub-hire) — PO tax axis
