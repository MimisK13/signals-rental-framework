<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Models\Address;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class DeleteAddress
{
    public function __invoke(Address $address): void
    {
        Gate::authorize('members.edit');

        event(new AuditableEvent($address, 'member.address.deleted'));

        $address->delete();
    }
}
