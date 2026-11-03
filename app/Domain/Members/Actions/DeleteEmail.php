<?php

declare(strict_types=1);

namespace App\Domain\Members\Actions;

use App\Domain\Members\Models\Email;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

class DeleteEmail
{
    public function __invoke(Email $email): void
    {
        Gate::authorize('members.edit');

        event(new AuditableEvent($email, 'member.email.deleted'));

        $email->delete();
    }
}
