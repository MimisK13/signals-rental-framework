<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Data\EmailData;
use App\Domain\Members\Data\UpdateEmailData;
use App\Domain\Members\Models\Email;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class UpdateEmail
{
    public function __invoke(Email $email, UpdateEmailData $data): EmailData
    {
        Gate::authorize('members.edit');

        $email->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($email, 'member.email.updated'));

        return EmailData::fromModel($email->fresh());
    }
}
