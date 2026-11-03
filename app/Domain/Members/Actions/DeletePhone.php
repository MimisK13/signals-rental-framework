<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Models\Phone;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class DeletePhone
{
    public function __invoke(Phone $phone): void
    {
        Gate::authorize('members.edit');

        event(new AuditableEvent($phone, 'member.phone.deleted'));

        $phone->delete();
    }
}
