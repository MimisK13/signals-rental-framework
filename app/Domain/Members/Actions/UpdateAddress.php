<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Data\AddressData;
use App\Domain\Members\Data\UpdateAddressData;
use App\Domain\Members\Models\Address;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class UpdateAddress
{
    public function __invoke(Address $address, UpdateAddressData $data): AddressData
    {
        Gate::authorize('members.edit');

        $address->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($address, 'member.address.updated'));

        return AddressData::fromModel($address->fresh());
    }
}
