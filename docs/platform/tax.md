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

## Related documentation

- [Multi-Currency & Tax](/docs/platform/multi-currency) — currencies, exchange rates, base engine
- [Tax Classes](/docs/platform/tax-classes) — product/organisation classes and rule matrix
- [Invoicing](/docs/platform/invoicing) — frozen tax at checkout
- [Sub-Hire & Procurement](/docs/platform/sub-hire) — PO tax axis
