<?php

namespace App\Verbs\Events\Invoices\Concerns;

use App\Events\AuditableEvent;
use App\Models\Invoice;
use Thunk\Verbs\Event;
use Thunk\Verbs\Models\VerbEvent;

/**
 * Bridges an invoice Verbs event's projection into the audit pipeline.
 *
 * @mixin Event
 */
trait RecordsInvoiceAudit
{
    /**
     * @var array{user_id: int|null, ip_address: string|null, user_agent: string|null}|null
     */
    private ?array $resolvedInvoiceAuditActor = null;

    /**
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $oldValues
     */
    protected function recordInvoiceAudit(Invoice $invoice, string $action, ?array $newValues, ?array $oldValues = null): void
    {
        $actor = $this->invoiceAuditActor();

        event(new AuditableEvent(
            model: $invoice,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            verbEventId: $this->id,
            userId: $actor['user_id'],
            ipAddress: $actor['ip_address'],
            userAgent: $actor['user_agent'],
        ));
    }

    /**
     * @return array{user_id: int|null, ip_address: string|null, user_agent: string|null}
     */
    protected function invoiceAuditActor(): array
    {
        if ($this->resolvedInvoiceAuditActor !== null) {
            return $this->resolvedInvoiceAuditActor;
        }

        /** @var array<string, mixed> $metadata */
        $metadata = VerbEvent::query()->whereKey($this->id)->value('metadata') ?? [];

        $userId = $metadata['user_id'] ?? null;
        $ipAddress = $metadata['ip_address'] ?? null;
        $userAgent = $metadata['user_agent'] ?? null;

        return $this->resolvedInvoiceAuditActor = [
            'user_id' => $userId !== null ? (int) $userId : null,
            'ip_address' => $ipAddress !== null ? (string) $ipAddress : null,
            'user_agent' => $userAgent !== null ? (string) $userAgent : null,
        ];
    }
}
