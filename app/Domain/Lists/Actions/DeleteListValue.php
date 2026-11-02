<?php

declare(strict_types=1);

namespace App\Domain\Lists\Actions;

use App\Domain\Lists\Models\ListValue;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

final class DeleteListValue
{
    public function __invoke(ListValue $value): void
    {
        Gate::authorize('list-values.manage');

        event(new AuditableEvent($value, 'list_value.deleted'));

        $value->delete();
    }
}
