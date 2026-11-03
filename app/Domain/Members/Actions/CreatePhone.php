<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Data\CreatePhoneData;
use App\Domain\Members\Data\PhoneData;
use App\Domain\Members\Models\Member;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class CreatePhone
{
    public function __invoke(Member $member, CreatePhoneData $data): PhoneData
    {
        Gate::authorize('members.edit');

        $phone = $member->phones()->create($data->toArray());

        event(new AuditableEvent($member, 'member.phone.created'));

        return PhoneData::fromModel($phone);
    }
}
