<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Data\PhoneData;
use App\Domain\Members\Data\UpdatePhoneData;
use App\Domain\Members\Models\Phone;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class UpdatePhone
{
    public function __invoke(Phone $phone, UpdatePhoneData $data): PhoneData
    {
        Gate::authorize('members.edit');

        $phone->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($phone, 'member.phone.updated'));

        return PhoneData::fromModel($phone->fresh());
    }
}
