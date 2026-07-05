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

## Using it in the app

Finance screens live under the **Finance** section of the main sidebar and header mega-menu. The whole Finance block — Invoices, Credit Notes, and Payments — is visible only when the user holds **`invoices.access`**. Individual pages then check finer-grained invoice permissions (`invoices.view`, `invoices.create`, and so on).

| Screen | Route | Mount permission |
|--------|-------|------------------|
| Invoices index | `/invoices` | `invoices.access` |
| Credit notes index | `/credit-notes` | `invoices.view` |
| Payments index | `/payments` | `invoices.view` |
| Invoice detail | `/invoices/{invoice}` | `view` policy on invoice |
| Invoice communications | `/invoices/{invoice}/communications` | `view` policy on invoice |
| Opportunity Invoices tab | `/opportunities/{opportunity}/invoices` | `invoices.view` |
| Member Invoices tab | `/members/{member}/invoices` | `invoices.view` |

### Finance index pages

All three Finance list pages use the shared **data table** with column toggles, export, Ransack-style column filters, and a **custom view** selector (Default / System / Shared / My Custom Views). Saved views drive columns, filters, and sort — the same machinery as other Phase-4 index pages.

**Invoices** (`/invoices`) shows status chips across the top (All, Draft, Issued, Paid, Overdue, and so on) with live counts. Columns include Number, Type, Status, Member, Store, Total, Outstanding, and Issued. Overdue invoices carry a red **Overdue** badge in the Status column. The toolbar offers **New Draft** when the user has `invoices.create`.

**Credit Notes** (`/credit-notes`) mirrors the same pattern with status chips and columns for Number, Invoice, Member, Issued, Total, and Status. The header mega-menu labels this area **Refunds & adjustments**.

**Payments** (`/payments`) lists every payment and refund recorded against invoices. Columns include Reference, Invoice, Member, Method, Paid date, Amount, and Type — refunds appear with an amber **Refund** badge.

### Invoice detail — quote to cash

Open any invoice from the index, an opportunity, or a member tab. The page header shows the invoice number, **status badge** (colour-coded — red for overdue), **type badge**, and deposit/pro-forma chips when applicable.

Header actions are policy-gated and appear only when legal for the current status:

| Action | Permission | When shown |
|--------|------------|------------|
| Edit | `invoices.edit` | Draft only |
| Issue | `invoices.issue` | Draft |
| Record Payment | `payments.record` | Issued with balance |
| Credit Note | `invoices.credit` | Issued with creditable balance |
| Download PDF | — | Non-draft |
| Email | — | Non-draft (dispatches billing communication) |
| Void | `invoices.void` | Issued per policy |
| Delete | `invoices.delete` | Draft only |

The **Overview** tab shows line items, payments, credit notes, and a summary sidebar (subtotal, tax, total, paid, outstanding). When the viewer has **`costs.view`**, line items gain a **Margin** column and the summary panel shows an aggregate **Margin** figure; without that permission the cost columns are omitted entirely — totals remain visible.

The **Communications** tab lists billing emails and related customer communications for this invoice (channel, status, subject, timeline) using the same log table component as the admin Communication Log.

**Credit notes from invoice show:** click **Credit Note** to open a modal. Choose **full credit** or partial credit with per-line quantities capped at the remaining creditable amount. Notes are optional. Issued credit notes appear in the Credit Notes panel and on `/credit-notes`.

**Payments:** **Record Payment** opens a modal pre-filled with the outstanding balance. Enter amount, method (bank transfer, card, cash, and so on), and an optional reference.

PDF download uses an existing generated document when available; otherwise it queues generation and prompts you to retry shortly. **Email** triggers the invoice billing communication through the notification engine.

### Opportunity Invoices tab

On an opportunity, open the **Invoices** tab (`/opportunities/{opportunity}/invoices`, badge = invoice count). The tab requires **`invoices.view`**; generation buttons require **`invoices.create`** plus an allowed opportunity source state.

| Button | Creates |
|--------|---------|
| **Invoice** | Standard invoice from the active quote (hidden when fully invoiced) |
| **Generate Deposit** | Deposit invoice using the default deposit percentage setting |
| **Generate Pro-forma** | Pro-forma invoice |

When every order line has been fully invoiced, the primary **Invoice** button is replaced by a **Fully invoiced** label — no further standard invoices can be generated from the UI. Progressive partial-quantity checkout is supported by the API (`lines` on the checkout payload) but is **not** exposed as a line-selection UI on this tab yet.

Successful generation redirects to the new invoice's detail page.

### Member Invoices tab and statement

The member **Invoices** tab (`/members/{member}/invoices`) shows an **Outstanding Balance** summary panel and a table of all invoices for that member. Users with **`members.view`** can pick a **Statement store** and click **Email Statement** to dispatch a member statement communication (`SendMemberStatement`).

### Permissions summary

| Permission | UI effect |
|------------|-----------|
| `invoices.access` | Finance nav links (Invoices, Credit Notes, Payments) |
| `invoices.view` | View lists, opportunity/member tabs, invoice detail |
| `invoices.create` | New Draft, opportunity generate buttons |
| `invoices.edit` | Edit draft header |
| `invoices.issue` | Issue action |
| `invoices.void` | Void action |
| `invoices.delete` | Delete draft |
| `invoices.credit` | Credit Note modal |
| `payments.record` | Record Payment modal |
| `costs.view` | Invoice margin column and summary |

Recording **refunds** and **write-offs** are not yet exposed in the invoice detail UI — use the [Payments API](/docs/api/payments) for refunds.

## Related documentation

- [Tax & Compliance](/docs/platform/tax) — zone-aware tax frozen at checkout
- [Documents & Templates](/docs/platform/documents) — PDF rendering and numbering
- [Notifications & Comms](/docs/platform/notifications) — billing email delivery
- [Invoices API](/docs/api/invoices) — REST surface and abilities
