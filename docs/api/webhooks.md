---
title: Webhooks
description: Subscribe to real-time event notifications delivered to your server via HTTPS.
---

## Overview

Webhooks let you receive real-time notifications when events happen in Signals. Register an endpoint URL, choose which events to subscribe to, and Signals will send a signed HTTP POST to your server whenever those events occur.

## Managing Webhooks

Webhooks can be managed via the API (`/api/v1/webhooks`) or from the admin panel at **Admin > Settings > Webhooks**.

## Events

### Members

| Event | Trigger |
|-------|---------|
| `member.created` | A new member is created |
| `member.updated` | A member's details are updated |
| `member.archived` | A member is archived |
| `member.restored` | An archived member is restored |
| `member.deleted` | A member is permanently deleted |
| `member.merged` | Two members are merged (secondary archived into primary) |
| `member.anonymised` | A member's personally identifiable information is erased |
| `member.vat_validated` | A member's VAT number is validated (VIES or manual confirmation) |

### Products

| Event | Trigger |
|-------|---------|
| `product.created` | A new product is created |
| `product.updated` | A product's details are updated |
| `product.archived` | A product is archived (soft-deleted, recoverable) |
| `product.restored` | An archived product is restored |
| `product.deleted` | A product is deleted |
| `product.merged` | Two products are merged |
| `product_group.created` | A new product group is created |
| `product_group.updated` | A product group is updated |
| `product_group.deleted` | A product group is deleted |

### Stock

| Event | Trigger |
|-------|---------|
| `stock_level.created` | A stock level record is created |
| `stock_level.updated` | A stock level is updated |
| `stock_level.deleted` | A stock level is deleted |
| `stock_transaction.created` | A stock transaction is recorded |
| `stock_transaction.deleted` | A stock transaction is deleted |

### Containers

| Event | Trigger |
|-------|---------|
| `container.created` | A container is created (product-backed or temporary) |
| `container.item.packed` | An item is packed into a container |
| `container.item.unpacked` | An item is unpacked from a container |
| `container.item.transferred` | An item is moved between containers in one step |
| `container.nested` | A container is packed inside another container |
| `container.sealed` | A container passes the seal gate and is sealed |
| `container.unsealed` | A sealed container is reopened (manual or transfer-triggered) |
| `container.dissolved` | A container is manually or dispatch-dissolved |
| `container.dispatched` | A kit/hybrid container is dispatched as a persistent unit |
| `container.returned` | A kit/hybrid container is returned from an opportunity |
| `container.repack.started` | Repack-on-return initiated for a transport container housing |
| `container.repack.item` | An item is returned and repacked during a repack session |

### Activities

| Event | Trigger |
|-------|---------|
| `activity.created` | A new activity is created |
| `activity.updated` | An activity is updated |
| `activity.deleted` | An activity is deleted |
| `activity.completed` | An activity is marked as completed |

### Rate Definitions

| Event | Trigger |
|-------|---------|
| `rate_definition.created` | A rate definition is created |
| `rate_definition.updated` | A rate definition is updated |
| `rate_definition.deleted` | A rate definition is deleted |
| `product_rate.created` | A product rate is assigned |
| `product_rate.updated` | A product rate is updated |
| `product_rate.deleted` | A product rate is removed |

### Tax

| Event | Trigger |
|-------|---------|
| `tax_rate.created` | A tax rate is created |
| `tax_rate.updated` | A tax rate is updated |
| `tax_rate.deleted` | A tax rate is deleted |
| `tax_rule.created` | A tax rule is created |
| `tax_rule.updated` | A tax rule is updated |
| `tax_rule.deleted` | A tax rule is deleted |

### Users

| Event | Trigger |
|-------|---------|
| `user.created` | A new user is invited or created |
| `user.updated` | A user's profile or roles are updated |
| `user.deactivated` | A user account is deactivated |
| `user.deleted` | A user is deleted |

### Roles

| Event | Trigger |
|-------|---------|
| `role.created` | A new role is created |
| `role.updated` | A role's name or permissions are modified |
| `role.deleted` | A role is deleted |

### Settings

| Event | Trigger |
|-------|---------|
| `settings.updated` | Application settings are changed |

### Opportunities

Opportunity events fire on every committed mutation of an opportunity, its line
items, costs, allocated assets, and quote versions. They are never re-delivered
when the event store is rebuilt.

| Event | Trigger |
|-------|---------|
| `opportunity.created` | A new opportunity (quote) is created |
| `opportunity.updated` | An opportunity's header fields are updated |
| `opportunity.quoted` | An opportunity is marked as quoted |
| `opportunity.converted_to_order` | A quote is converted to an order |
| `opportunity.reverted_to_quotation` | An order is reverted back to a quotation |
| `opportunity.reverted_to_draft` | A quotation is reverted back to a draft |
| `opportunity.reinstated` | A lost, dead, postponed, or cancelled opportunity is reinstated |
| `opportunity.reopened` | A completed order is re-opened back to an active order |
| `opportunity.locks_applied` | FX/tax locks are applied to an order |
| `opportunity.locks_released` | An order's FX/tax locks are released for re-pricing |
| `opportunity.status_changed` | An opportunity's status changes |
| `opportunity.status_promoted` | An opportunity's status is promoted along its workflow |
| `opportunity.cloned` | An opportunity is cloned into a new opportunity |
| `opportunity.deleted` | An opportunity is soft-deleted |
| `opportunity.restored` | A soft-deleted opportunity is restored |
| `opportunity.deal_price_set` | A negotiated deal total is set |
| `opportunity.deal_price_cleared` | A negotiated deal total is cleared |
| `opportunity.group_deal_price_set` | A negotiated deal total is set on a line-item group |
| `opportunity.group_deal_price_cleared` | A negotiated deal total is cleared from a line-item group |
| `opportunity.pricing_recalculated` | Opportunity pricing is recalculated (rates, tax, surcharges) |
| `opportunity.discount_reset` | Applied discounts are cleared from an opportunity |
| `opportunity.discount_category_applied` | A discount category is applied to an opportunity |
| `opportunity.discount_manually_overridden` | A discount category percentage is manually overridden |
| `opportunity.item_added` | A line item is added |
| `opportunity.item_details_updated` | A line item's details (description, notes, etc.) are updated |
| `opportunity.item_renamed` | A line item is renamed |
| `opportunity.item_removed` | A line item is removed |
| `opportunity.item_quantity_changed` | A line item's quantity changes |
| `opportunity.item_dates_changed` | A line item's hire dates change |
| `opportunity.item_discount_set` | A line item discount is set |
| `opportunity.item_optional_toggled` | A line item is toggled optional/required |
| `opportunity.item_price_overridden` | A line item unit price is overridden |
| `opportunity.item_substituted` | A line item product is substituted |
| `opportunity.item_surcharge_overridden` | A line item surcharge amount is manually overridden |
| `opportunity.items_restructured` | An opportunity's line-item tree is restructured |
| `opportunity.items_reordered` | An opportunity's line items are reordered |
| `opportunity.surcharge_group_toggled` | A surcharge group is enabled or disabled on an opportunity |
| `opportunity.cost_added` | An additional cost is added |
| `opportunity.cost_updated` | An additional cost is updated |
| `opportunity.cost_removed` | An additional cost is removed |
| `opportunity.version_created` | A new quote version is created |
| `opportunity.version_activated` | A quote version is made active |
| `opportunity.version_accepted` | A quote version is accepted by the customer |
| `opportunity.version_declined` | A quote version is declined |
| `opportunity.version_sent` | A quote version is sent to the customer |
| `opportunity.version_relabelled` | A quote version's label is changed |
| `opportunity.version_superseded` | A quote version is superseded by a newer one |
| `opportunity.version_deleted` | A quote version is deleted |
| `opportunity.asset_allocated` | A serialised asset is allocated to a line item |
| `opportunity.asset_deallocated` | A serialised asset is deallocated |
| `opportunity.asset_substituted` | An allocated asset is substituted for another |
| `opportunity.asset_prepared` | An asset is marked prepared for dispatch |
| `opportunity.asset_preparation_reverted` | An asset's prepared state is reverted |
| `opportunity.asset_checked` | An asset is scan-checked |
| `opportunity.asset_dispatched` | An asset is dispatched |
| `opportunity.asset_on_hire` | An asset is marked on-hire |
| `opportunity.asset_returned` | An asset is returned |
| `opportunity.asset_status_reverted` | An asset's lifecycle status is reverted |
| `opportunity.asset_container_set` | An asset is placed in a container |
| `opportunity.asset_container_cleared` | An asset is removed from its container |
| `opportunity.bulk_dispatched` | A bulk (non-serialised) quantity is dispatched |
| `opportunity.bulk_returned` | A bulk quantity is returned |
| `opportunity.bulk_quantity_adjusted` | A bulk dispatched/returned quantity is adjusted |

### Documents

| Event | Trigger |
|-------|---------|
| `document.created` | A document is generated from a template |
| `document.pdf_generated` | A PDF is generated and stored for a document |
| `document.finalised` | A document is finalised (locked for sharing) |
| `document.voided` | A finalised document is voided |
| `document.emailed` | A document is emailed to a recipient (Notification Engine, P38) |
| `document.public_link_created` | A public sharing link is created for a document |
| `document.public_link_accessed` | A public sharing link is accessed for the first time |
| `document.public_link_revoked` | A public sharing link is revoked |
| `document.public_link_configured` | A public link's expiry or PIN settings are changed |
| `document_template.created` | A document template is created or duplicated |
| `document_template.updated` | A document template is saved |
| `document_template.deleted` | A custom document template is deleted |
| `document_template.restored` | A system template is restored to its shipped default |
| `document_template.set_as_default` | A template is marked as the default for its type |
| `document_sequence.reset` | A document numbering sequence rolls over to a new period |

### Invoices

| Event | Trigger |
|-------|---------|
| `invoice.created` | A draft invoice is generated |
| `invoice.issued` | A draft invoice is issued to the customer |
| `invoice.voided` | An invoice is voided |
| `invoice.overdue` | An issued invoice passes its due date with an outstanding balance |
| `payment.recorded` | A payment is recorded against an invoice — payload embeds payment fields (see [Invoices API](/docs/api/invoices)) |
| `payment.refunded` | A refund is recorded against an invoice — payload embeds payment/refund fields (see [Invoices API](/docs/api/invoices)) |
| `credit_note.issued` | A credit note is issued against an invoice |

### Availability

| Event | Trigger |
|-------|---------|
| `availability.changed` | A product/store's availability read model has been recalculated |

### Shortages

| Event | Trigger |
|-------|---------|
| `shortage.detected` | A shortage is detected for an order line |
| `shortage.cleared` | A previously-detected shortage no longer exists |
| `shortage.acknowledged` | A shortage is acknowledged at a confirmation gate |
| `shortage.resolution.created` | A shortage resolution is created |
| `shortage.resolution.confirmed` | A shortage resolution is confirmed |
| `shortage.resolution.in_progress` | A shortage resolution's fulfilment has begun |
| `shortage.resolution.fulfilled` | A shortage resolution completes and stock is available |
| `shortage.resolution.failed` | A shortage resolution attempt fails |
| `shortage.resolution.cancelled` | A shortage resolution is cancelled |
| `shortage.waitlist.created` | A waitlist monitor is placed on a shortage |
| `shortage.waitlist.matched` | Monitored stock becomes available |
| `shortage.waitlist.expired` | A waitlist monitor expires without matching |
| `shortage.escalated` | An unresolved shortage passes the configured escalation threshold |

### Purchase Orders

| Event | Trigger |
|-------|---------|
| `purchase_order.created` | A draft purchase order is created |
| `purchase_order.updated` | A draft purchase order is updated |
| `purchase_order.sent` | A purchase order is sent to the supplier |
| `purchase_order.received` | Goods are received against a purchase order |
| `purchase_order.cancelled` | A purchase order is cancelled |
| `purchase_order.deleted` | A draft purchase order is deleted |

### Virtual Stock

| Event | Trigger |
|-------|---------|
| `virtual_stock_intake.created` | A draft virtual stock intake is created |
| `virtual_stock_intake.updated` | A draft virtual stock intake is updated |
| `virtual_stock_intake.confirmed` | A virtual stock intake is confirmed |
| `virtual_stock_intake.received` | Virtual stock is received into availability |
| `virtual_stock_intake.returned` | Virtual stock is returned after the hire window |
| `virtual_stock_intake.cancelled` | A virtual stock intake is cancelled |
| `virtual_stock_intake.deleted` | A draft virtual stock intake is deleted |

## Payload Format

Every webhook delivery sends a JSON POST request with the event name and associated data:

```json
{
    "event": "user.created",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": {
        "user": {
            "id": 42,
            "name": "Jane Smith",
            "email": "jane@example.com"
        }
    }
}
```

## Payload Schemas

Most events embed the affected resource under a singular key inside `data`, using the same shape the REST API returns. Lifecycle events that only need to identify a record (deletions, archive/restore) send just an `id`.

### Tax Rate Events

`tax_rate.created` and `tax_rate.updated` include the full tax rate object:

```json
{
    "event": "tax_rate.created",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": {
        "tax_rate": {
            "id": 1,
            "name": "UK Standard",
            "description": "Standard rate VAT",
            "rate": "20.0000",
            "is_active": true,
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Tax rate identifier |
| `name` | string | Display name (e.g. `UK Standard`) |
| `description` | string\|null | Optional description |
| `rate` | string | Percentage as a decimal string (e.g. `20.0000`) |
| `is_active` | boolean | Whether the rate is active |
| `created_at` / `updated_at` | string | ISO 8601 UTC timestamps |

`tax_rate.deleted` sends only the identifier:

```json
{
    "event": "tax_rate.deleted",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": { "id": 1 }
}
```

### Tax Rule Events

`tax_rule.created` and `tax_rule.updated` include the full tax rule object:

```json
{
    "event": "tax_rule.created",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": {
        "tax_rule": {
            "id": 5,
            "organisation_tax_class_id": 1,
            "product_tax_class_id": 2,
            "tax_rate_id": 1,
            "priority": 10,
            "is_active": true,
            "created_at": "2026-01-15T14:30:00Z",
            "updated_at": "2026-01-15T14:30:00Z"
        }
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Tax rule identifier |
| `organisation_tax_class_id` | integer | Organisation tax class matched by the rule |
| `product_tax_class_id` | integer | Product tax class matched by the rule |
| `tax_rate_id` | integer | Tax rate applied when the rule matches |
| `priority` | integer | Higher priority rules win when several match |
| `is_active` | boolean | Whether the rule is active |
| `created_at` / `updated_at` | string | ISO 8601 UTC timestamps |

`tax_rule.deleted` sends only the identifier:

```json
{
    "event": "tax_rule.deleted",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": { "id": 5 }
}
```

### Product Archive & Restore Events

`product.archived` and `product.restored` are lifecycle transitions on an existing product and carry only its identifier. Fetch the current state from `GET /api/v1/products/{id}` if you need the full record.

```json
{
    "event": "product.archived",
    "timestamp": "2026-01-15T14:30:00Z",
    "data": { "id": 42 }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Product identifier |

## Verifying Signatures

Every delivery includes an `X-Signals-Signature` header containing an HMAC-SHA256 signature of the request body. Verify it against the secret you received when creating the webhook:

```php
$signature = hash_hmac('sha256', $requestBody, $webhookSecret);

if (! hash_equals($signature, $request->header('X-Signals-Signature'))) {
    abort(401, 'Invalid signature');
}
```

## Retry Behaviour

Failed deliveries (non-2xx response or connection timeout) are retried with exponential backoff up to 6 times over approximately 12 hours. After 3 consecutive days of failures, the webhook is automatically disabled.

Re-enable a disabled webhook from the admin panel or by updating it via the API with `is_active: true`.

## Delivery Logs

Every delivery attempt is logged. View logs via the API at `GET /api/v1/webhooks/{id}/logs` or from the API Console **Webhooks** page by clicking the logs icon on a webhook row. Deliveries also appear in the unified **Request Log** on the `webhook_sent` channel.

## Outbound metering weights

Each outbound event carries a **price weight** (0–4) declared in `WebhookEventRegistry`. Weights default to **1** unless overridden at registration time. On the first delivery attempt of each job run, `DeliverWebhook` records the weight on the `webhook_logs` row and increments the `webhook_sent` daily rollup by the event's weight.

Weight **0** events are still logged but contribute zero **weighted units** toward usage totals.

## Inbound webhook receiver

Signals can accept webhook POSTs from external systems via registered **inbound sources**. Manage sources from the API Console at `/api/webhooks` (Inbound tab) or programmatically when that surface is exposed.

### Receive URL

```
POST {your-app-url}/webhooks/receive/{slug}
```

Replace `{slug}` with the source's URL slug. The route is public (no Sanctum token) — authentication is HMAC signature verification only.

### Signature verification

Each source stores a secret, signature algorithm (default `sha256`), and header name (default `X-Signature`). The middleware computes:

```php
$expected = hash_hmac($source->signature_algo, $requestBody, $source->secret);
$provided = $request->header($source->signature_header);

$valid = is_string($provided) && hash_equals($expected, $provided);
```

Send the HMAC digest as a plain string in the configured header (not prefixed with `sha256=`).

### Responses

| Status | Condition | Body |
|--------|-----------|------|
| `200` | Valid signature | `{"status":"received"}` — fires `InboundWebhookReceived` |
| `401` | Missing or invalid signature | `{"message":"Invalid signature"}` — logged with weight 0 |
| `404` | Unknown slug or inactive source | Laravel 404 page |

### Inbound metering weights

Unlike outbound events (code-defined in `WebhookEventRegistry`), inbound weights are stored per source in `inbound_webhook_sources.price_weight` (0–4, editable in the console). Valid requests meter on the `webhook_received` channel; invalid signatures log detail rows but do not increment weighted units.

### Inbound logs

Receipts are stored in `inbound_webhook_logs` and surfaced in the Request Log (`webhook_received` channel) and the source's logs modal in the API Console.
