<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Services;

use App\Domain\Quotations\Events\QuotationDomainEvent;
use App\Domain\Quotations\Models\Quotation;
use App\Events\AuditableEvent;

final class QuotationAudit
{
    /** @param array<string, mixed> $metadata */
    public function record(Quotation $quotation, string $action, array $metadata = []): void
    {
        event(new AuditableEvent($quotation, $action, metadata: $metadata));
        QuotationDomainEvent::dispatch($action, $quotation->id);
    }
}
