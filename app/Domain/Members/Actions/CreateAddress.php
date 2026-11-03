<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Data\AddressData;
use App\Domain\Members\Data\CreateAddressData;
use App\Domain\Members\Models\Member;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class CreateAddress
{
    public function __invoke(Member $member, CreateAddressData $data): AddressData
    {
        Gate::authorize('members.edit');

        $address = $member->addresses()->create($data->toArray());

        event(new AuditableEvent($member, 'member.address.created'));

        return AddressData::fromModel($address);
    }
}
