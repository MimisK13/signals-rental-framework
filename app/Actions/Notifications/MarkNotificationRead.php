<?php

namespace App\Actions\Notifications;

use App\Models\Member;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MarkNotificationRead
{
    public function __invoke(User $user, string $notificationId): void
    {
        $member = $this->memberFor($user);

        $notification = $member->notifications()->whereKey($notificationId)->first();

        if ($notification === null) {
            throw ValidationException::withMessages([
                'notification' => ['Notification not found.'],
            ]);
        }

        $notification->markAsRead();
    }

    public function markAll(User $user): void
    {
        $member = $this->memberFor($user);

        $member->unreadNotifications->markAsRead();
    }

    private function memberFor(User $user): Member
    {
        $member = $user->member;

        if ($member === null) {
            throw ValidationException::withMessages([
                'member' => ['No member profile is linked to this account.'],
            ]);
        }

        return $member;
    }
}
