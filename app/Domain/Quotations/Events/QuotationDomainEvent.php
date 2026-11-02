<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class QuotationDomainEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public string $name, public int $quotationId)
    {
        //
    }
}
