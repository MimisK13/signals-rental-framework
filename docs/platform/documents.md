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

## Related documentation

- [Invoicing](/docs/platform/invoicing) — invoice and credit-note PDFs
- [Document Templates API](/docs/api/document-templates)
- [Documents API](/docs/api/documents)
- [Admin Panel](/docs/platform/admin-panel) — finance, numbering, and template admin routes
