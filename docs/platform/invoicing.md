---
title: Invoicing & Payments
description: Quote-to-cash — checkout from opportunities, invoice lifecycle, payments, credit notes, deposit/pro-forma invoices, and billing communications.
---

## Overview

Phase 4 closes the **quote-to-cash** loop: an opportunity converts to an invoice with a **frozen tax snapshot**, the invoice is issued and paid, credit notes reconcile adjustments, and PDFs and billing emails reach the customer through the [Document Engine](/docs/platform/documents) and [Notification Engine](/docs/platform/notifications).

Invoices, payments, and credit notes are **Verbs event streams** with projected read models — the same pattern as opportunities. Reads hit the projection; writes fire events and dual-write the read model.

Finance list pages live under the **Finance** navigation section, gated on finance permissions (`invoices.access`, `credit-notes.access`, `payments.access`).

| Area | Route |
|------|-------|
| Invoices index | `/invoices` |
| Create invoice | `/invoices/create` |
| Invoice detail | `/invoices/{invoice}` |
| Invoice communications | `/invoices/{invoice}/communications` |
| Credit notes | `/credit-notes` |
| Payments | `/payments` |
| Opportunity checkout tab | `/opportunities/{opportunity}/invoices` |
| Member invoices tab | `/members/{member}/invoices` |

Bare `/finance/credit-notes` and `/finance/payments` URLs redirect with **301** to the canonical routes above.

## Checkout

**Checkout** generates a draft invoice from an opportunity's active quote version, copying line items, costs, surcharges, and tax with a penny-reconciliation invariant at fire time. Tax is **frozen** on the invoice — later opportunity changes do not alter issued documents.

Checkout is available from:

- The opportunity **Invoices** tab in the UI
- `POST /api/v1/opportunities/{id}/invoices` — see [Invoices API](/docs/api/invoices)

### Invoice types

| Type | Purpose |
|------|---------|
| **Standard** | Full invoice from the opportunity (default checkout type) |
| **Deposit** | Deposit invoice with dedicated partial-invoice tax policy |
| **Pro-forma** | Pro-forma invoice before final billing |

Pass `type` in the checkout request body to select deposit or pro-forma generation.

### Source states

Checkout validates that the opportunity is in an allowed **source state** for invoicing (configured in `invoices.source_states`). Attempting checkout from a disallowed state returns a validation error.

### Idempotency

Financial POST endpoints honour the **`Idempotency-Key`** header to prevent duplicate side effects on retry. Scoped to the bearer token; replays stored 2xx/422 responses for up to 24 hours. Applies to checkout, payment recording, and refunds.

## Invoice lifecycle

| Status | Meaning |
|--------|---------|
| **Draft** | Editable header; not yet issued to the customer |
| **Issued** | Finalised; payments and credit notes apply |
| **Paid** | Fully settled (derived from balance) |
| **Overdue** | Issued past due date with outstanding balance |
| **Void** | Cancelled issued invoice |

### Actions

| Action | When | Notes |
|--------|------|-------|
| **Issue** | Draft → Issued | Assigns document number; locks lines |
| **Void** | Issued (unpaid or partially paid per policy) | Negates billing intent |
| **Write-off** | Issued with uncollectable balance | Closes outstanding amount |

**Partial-quantity invoicing** lets you bill only part of an order line — useful when equipment is dispatched in stages.

Draft invoices can be edited (header fields) or soft-deleted. Issued invoices are immutable except through payments, credit notes, void, or write-off.

## Payments and refunds

Record **payments** against issued invoices from the invoice detail page or via `POST /api/v1/invoices/{id}/payments`. The running **balance** (`amount_outstanding`) updates across payments and credits.

**Refunds** reverse a prior payment via `POST /api/v1/invoices/{id}/refunds`. Receipt documents can be generated through the Document Engine.

There is **no standalone** `GET /api/v1/payments` list endpoint — payments are accessed through invoice nested routes and the Finance UI index. See [Payments API](/docs/api/payments).

## Credit notes

**Credit notes** mirror invoice tax with negated amounts. Issue full or partial credit from an invoice. Credit-note PDFs render through document templates.

List and show credit notes at `/credit-notes` or via `GET /api/v1/credit_notes`. See [Credit Notes API](/docs/api/credit-notes).

## PDFs and email

- **Invoice PDF** — `GET /api/v1/invoices/{id}/pdf` or download from the UI
- **Credit note PDF** — generated via the Document Engine
- **Billing email** — `POST /api/v1/invoices/{id}/email` dispatches invoice, receipt, or statement communications through merge-field templates

Human-readable **download filenames** are set on every document download path.

## Invoice margin

When the viewer has **`costs.view`**, invoice detail shows real margin figures computed from line costs. Users without cost visibility see totals without cost breakdown — cost fields are gated, not whole resources.

## Money in API responses

All monetary fields serialise as **decimal strings** (e.g. `"125.50"`), not floats or raw integers. Values are stored in integer minor units internally. Tax follows the framework's **ex-tax** model with tax computed finally and grouped by tax class.

## Related documentation

- [Tax & Compliance](/docs/platform/tax) — zone-aware tax frozen at checkout
- [Documents & Templates](/docs/platform/documents) — PDF rendering and numbering
- [Notifications & Comms](/docs/platform/notifications) — billing email delivery
- [Invoices API](/docs/api/invoices) — REST surface and abilities
