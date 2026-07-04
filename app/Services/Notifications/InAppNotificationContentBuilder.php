<?php

namespace App\Services\Notifications;

use App\Events\AuditableEvent;
use App\Models\Invoice;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ShortageResolution;
use App\Models\ShortageWaitlistMonitor;
use App\Models\VirtualStockIntake;

/**
 * Builds structured in-app notification title/body from domain audit events.
 *
 * System notifications use code-built content per the notification engine plan;
 * communication templates apply to email/SMS only.
 */
class InAppNotificationContentBuilder
{
    /**
     * @return array{title: string, body: string}
     */
    public function build(AuditableEvent $event, NotificationType $type): array
    {
        $body = $this->buildBody($event, $type);

        return [
            'title' => $type->name,
            'body' => $body ?? $type->description,
        ];
    }

    private function buildBody(AuditableEvent $event, NotificationType $type): ?string
    {
        if ($event->model instanceof Opportunity) {
            return $this->bodyForOpportunityEvent($event, $type->key);
        }

        if ($event->model instanceof Invoice) {
            return $this->bodyForInvoiceEvent($event, $type->key);
        }

        if ($event->model instanceof ShortageWaitlistMonitor) {
            return $this->bodyForWaitlistMonitor($event->model, $type->key);
        }

        if ($event->model instanceof ShortageResolution) {
            return $this->bodyForShortageResolution($event->model, $type->key);
        }

        if ($event->model instanceof VirtualStockIntake) {
            return $this->bodyForVirtualStockIntake($event->model, $type->key);
        }

        return null;
    }

    private function bodyForOpportunityEvent(AuditableEvent $event, string $typeKey): ?string
    {
        /** @var Opportunity $opportunity */
        $opportunity = $event->model;
        $opportunity->loadMissing('member');

        $subject = $this->quoted($opportunity->subject);
        $memberName = $opportunity->member?->name;

        if ($typeKey === 'shortage.escalated') {
            $productName = is_string($event->newValues['product_name'] ?? null)
                ? $event->newValues['product_name']
                : null;

            if ($productName === null) {
                return null;
            }

            return sprintf(
                'Unresolved shortage for %s on opportunity %s is approaching dispatch.',
                $productName,
                $subject,
            );
        }

        return match ($typeKey) {
            'opportunity.created' => $memberName !== null
                ? sprintf('Opportunity %s was created for %s.', $subject, $memberName)
                : sprintf('Opportunity %s was created.', $subject),
            'opportunity.confirmed' => $memberName !== null
                ? sprintf('Opportunity %s was confirmed for %s.', $subject, $memberName)
                : sprintf('Opportunity %s was confirmed.', $subject),
            'opportunity.cancelled' => sprintf('Opportunity %s was cancelled.', $subject),
            'opportunity.on_hold' => sprintf('Opportunity %s was placed on hold.', $subject),
            'opportunity.checked_out' => sprintf('Equipment dispatched for opportunity %s.', $subject),
            'opportunity.checked_in' => sprintf('Equipment returned for opportunity %s.', $subject),
            'opportunity.late_return' => sprintf('Equipment for opportunity %s has not been returned.', $subject),
            'opportunity.assigned' => sprintf('Opportunity %s ownership was assigned or changed.', $subject),
            'opportunity.quote_expiring' => sprintf('Quote for opportunity %s is approaching its expiry date.', $subject),
            default => sprintf('Opportunity %s was updated.', $subject),
        };
    }

    private function bodyForInvoiceEvent(AuditableEvent $event, string $typeKey): string
    {
        /** @var Invoice $invoice */
        $invoice = $event->model;
        $invoice->loadMissing('member');

        $number = $invoice->number;
        $total = $invoice->formatMoneyCost('total');
        $memberName = $invoice->member?->name;

        return match ($typeKey) {
            'invoice.overdue' => $memberName !== null
                ? sprintf('Invoice %s for %s is overdue for %s.', $number, $total, $memberName)
                : sprintf('Invoice %s for %s is overdue.', $number, $total),
            'invoice.payment_received' => $memberName !== null
                ? sprintf('Payment recorded against invoice %s for %s.', $number, $memberName)
                : sprintf('Payment recorded against invoice %s.', $number),
            'invoice.created' => $memberName !== null
                ? sprintf('Invoice %s for %s was created for %s.', $number, $total, $memberName)
                : sprintf('Invoice %s for %s was created.', $number, $total),
            default => sprintf('Invoice %s was updated.', $number),
        };
    }

    private function bodyForWaitlistMonitor(ShortageWaitlistMonitor $monitor, string $typeKey): ?string
    {
        $monitor->loadMissing('product');

        $productName = $monitor->product?->name;
        $quantity = $monitor->quantity_needed;

        if ($productName === null) {
            return null;
        }

        return match ($typeKey) {
            'shortage.waitlist.matched' => sprintf(
                'Stock is now available for %s (quantity %d).',
                $productName,
                $quantity,
            ),
            'shortage.waitlist.created' => sprintf(
                '%s was added to the waitlist (quantity %d).',
                $productName,
                $quantity,
            ),
            default => sprintf('Waitlist monitor updated for %s.', $productName),
        };
    }

    private function bodyForShortageResolution(ShortageResolution $resolution, string $typeKey): ?string
    {
        $context = $this->shortageResolutionContext($resolution);

        if ($context === null) {
            return null;
        }

        ['product' => $productName, 'opportunity_subject' => $opportunitySubject] = $context;

        return match ($typeKey) {
            'shortage.resolution.confirmed' => sprintf(
                'Shortage resolution confirmed for %s on opportunity %s.',
                $productName,
                $this->quoted($opportunitySubject),
            ),
            'shortage.escalated' => sprintf(
                'Shortage escalated for %s on opportunity %s.',
                $productName,
                $this->quoted($opportunitySubject),
            ),
            default => sprintf('Shortage resolution updated for %s.', $productName),
        };
    }

    private function bodyForVirtualStockIntake(VirtualStockIntake $intake, string $typeKey): string
    {
        if ($typeKey !== 'virtual_stock.overrun') {
            return sprintf('Virtual stock intake %s was updated.', $intake->number);
        }

        $intake->loadMissing('product');

        $productName = $intake->product?->name;

        if ($productName === null) {
            return sprintf(
                'Virtual stock intake %s is still on hire past its available window.',
                $intake->number,
            );
        }

        return sprintf(
            'Virtual stock intake %s (%s) is still on hire past its available window.',
            $intake->number,
            $productName,
        );
    }

    /**
     * @return array{product: string, opportunity_subject: string}|null
     */
    private function shortageResolutionContext(ShortageResolution $resolution): ?array
    {
        $resolution->loadMissing([
            'items.opportunityItem.opportunity',
            'items.opportunityItem.item',
        ]);

        $item = $resolution->items->first()?->opportunityItem;

        if ($item === null) {
            return null;
        }

        $itemable = $item->item;

        $productName = $itemable instanceof Product
            ? $itemable->name
            : ($item->name ?: null);

        $opportunitySubject = $item->opportunity?->subject;

        if ($productName === null || $opportunitySubject === null) {
            return null;
        }

        return [
            'product' => $productName,
            'opportunity_subject' => $opportunitySubject,
        ];
    }

    private function quoted(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'Untitled';
        }

        return "'{$value}'";
    }
}
