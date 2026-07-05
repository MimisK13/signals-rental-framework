---
title: Documents & Templates
description: Per-store document templates, PDF rendering, numbering sequences, public sharing, and the template editor.
---

## Overview

The **Document Engine** renders PDFs and HTML from per-store **Blade templates** — invoices, credit notes, quotes, purchase orders, receipts, and other document types. Templates compile through a **sandboxed Blade compiler** with an allowlisted tag set; DomPDF drives PDF output with repeating headers and footers on every page.

Generated documents are first-class records with status, versioning, audit trail, and webhook events (`document.generated`, `document.finalised`, `document.voided`, `pdf_generated`).

## Template editor

**Routes:**

| Route | Purpose |
|-------|---------|
| `/admin/document-templates` | List templates by store and type |
| `/admin/document-templates/{template}/edit` | Three-panel editor |

The editor provides:

- **Field browser** — insert fields from the field registry
- **CodeMirror 6** source editor — sandboxed Blade source
- **Live preview** — rendered output against sample data
- **Version history** — diff, restore, export/import
- **Set default / restore default** — for system templates

Communication templates (billing emails) use a parallel editor at `/admin/settings/communication-templates/{template}/edit` — see [Notifications & Comms](/docs/platform/notifications).

## Generating documents

From the UI, generate documents on opportunities (**Documents** tab at `/opportunities/{opportunity}/documents`), invoices, credit notes, and other documentables.

Via API:

- `POST /api/v1/documents/generate` — single document (sync or async)
- `POST /api/v1/documents/batch_generate` — batch generation job
- `POST /api/v1/documents/{id}/finalise` — lock rendered output
- `POST /api/v1/documents/{id}/void` — void a document

See [Documents API](/docs/api/documents).

### Frozen regeneration

Finalised documents regenerate from **`rendered_data` snapshots** — the template at generation time is preserved even if the live template changes later.

## Numbering sequences

**Route:** `/admin/settings/numbering`

Per-entity numbering schemes (opportunities, invoices, credit notes, members, payments, purchase orders, etc.) are backed by **`document_sequences`** with store scoping and collision guards. The admin page shows a **live preview** of the next number for each scheme.

Opportunity numbering converges on the same sequence infrastructure as invoices and documents.

API: `GET` and `PUT /api/v1/document_sequences` — see [Document Sequences API](/docs/api/document-sequences).

## Public sharing

Share finalised documents via tokenised public links:

| Feature | Description |
|---------|-------------|
| **Token URL** | `{app-url}/d/{token}` (prefix configurable via `documents.public_route_prefix`) |
| **PIN protection** | Optional PIN verified at `POST /d/{token}/pin` |
| **Expiry** | Configurable link expiry |
| **QR code** | QR on shared links for mobile access |

Manage links via API: `POST`, `PUT`, and `DELETE /api/v1/documents/{id}/public_link`.

Public HTML responses are hardened against stored XSS; preview rendering enforces authorisation to prevent IDOR.

## Downloads

Authenticated downloads use **signed temporary URLs**. Every download path sets a human-readable filename derived from the document number and type.

## Store branding

Document rendering incorporates per-store branding (logo, colours) from store and company settings seeded with system templates on fresh installs.

## Using it in the app

Document administration lives under **Admin → Documents** (sidebar group `documents`).

| Screen | Route | Permission |
|--------|-------|------------|
| Numbering | `/admin/settings/numbering` | `numbering.view` / `numbering.edit` |
| Document templates index | `/admin/document-templates` | `document_templates.view` |
| Template editor | `/admin/document-templates/{template}/edit` | `document_templates.view`; save needs `document_templates.edit` |
| Opportunity Documents tab | `/opportunities/{opportunity}/documents` | Tab: `documents.view`; generate: `documents.create` |

Communication templates (billing emails) use a separate editor at `/admin/settings/communication-templates/{template}/edit` — see [Notifications & Comms](/docs/platform/notifications).

### Document templates index

The index is a full data table with custom views, column toggles, and export.

**Columns:** Name, Document Type, Store, Status badges (Default / System / Active / Inactive), Version, Last Edited, Actions

**Toolbar:** **New template** (when `document_templates.edit`) opens a modal with three starting points:

| Starting point | Use when |
|----------------|----------|
| Base on existing | Clone a template |
| Blank for document type | System starter for a type |
| Completely empty | From scratch |

**Row actions** (edit permission + store scope): Edit, Duplicate, Set default, Restore default (system templates), Delete (non-system)

### Three-panel template editor

Full-screen editor at `/admin/document-templates/{id}/edit`.

| Area | Contents |
|------|----------|
| **Field browser** | Searchable snippet list from the field registry — click to insert at cursor |
| **CodeMirror tabs** | Body, Header, Footer, Styles — sandboxed Blade source |
| **Live preview** | Rendered output; toggle **Sample** vs **Real entity** data; optional record picker; zoom control |
| **Version sidebar** | Version history with diff viewer and **Restore** |
| **Top bar** | Page, branding & numbering modal; Refresh preview; Save |

**Page setup modal** covers margins, page size, logo upload, and per-template document sequence (prefix, suffix, year/month toggles, next number, padding) with live next-number preview.

Open preview in a new window via the preview panel controls when you need a detached render.

### Numbering admin

**Route:** `/admin/settings/numbering`

Two sections:

1. **Per-store table** — Standard Invoice, Deposit Invoice, Pro Forma Invoice, Credit Note, Purchase Order, Virtual Stock Intake, Opportunity — each row shows a live **next number preview** and an edit pencil (when `numbering.edit`)
2. **Global** — Member and Payment numbers shared across stores

The edit modal exposes prefix, suffix, next number, digit padding, and include year/month switches with a live preview string.

### Opportunity and invoice documents

On an opportunity, the **Documents** tab (badge = document count) shows:

**Generate document card** — pick Template, Output (PDF or HTML), click **Generate**.

**Document table** — Number, Type, Status, Created, First viewed, Actions:

| Action | Permission |
|--------|------------|
| Download | Signed URL when file exists |
| Share | `documents.share` — opens public link modal |
| Copy link / QR | When link already exists |
| Configure | Update expiry / PIN on existing link |
| Revoke | Remove public link |

**Share modal:** optional expiry datetime, optional PIN (min 4 chars), QR code for the public URL, copy-to-clipboard. Public URLs use `/d/{token}` (configurable prefix).

**Finalise** and **void** document actions exist on the API but are **not** exposed as buttons on the opportunity Documents tab — generated documents move through statuses via backend rules and API calls.

Invoice PDFs from invoice detail use the billing document type for that invoice's store; see [Invoicing](/docs/platform/invoicing).

### Permissions summary

| Permission | UI effect |
|------------|-----------|
| `document_templates.view` | Templates index and editor (read) |
| `document_templates.edit` | Save template, row mutations, new template |
| `numbering.view` | Numbering admin page |
| `numbering.edit` | Edit sequence modal |
| `documents.view` | Opportunity Documents tab |
| `documents.create` | Generate document card |
| `documents.share` | Share / configure / revoke public links |

## Related documentation

- [Invoicing](/docs/platform/invoicing) — invoice and credit-note PDFs
- [Document Templates API](/docs/api/document-templates)
- [Documents API](/docs/api/documents)
- [Admin Panel](/docs/platform/admin-panel) — finance, numbering, and template admin routes
