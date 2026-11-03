<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Data\CreateEmailData;
use App\Domain\Members\Data\EmailData;
use App\Domain\Members\Models\Member;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class CreateEmail
{
    public function __invoke(Member $member, CreateEmailData $data): EmailData
    {
        Gate::authorize('members.edit');

        $email = $member->emails()->create($data->toArray());

        event(new AuditableEvent($member, 'member.email.created'));

        return EmailData::fromModel($email);
    }
}
