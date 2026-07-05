<?php

namespace App\Actions\Notifications;

use App\Models\Member;
use App\Services\Notifications\CommunicationPreferences;

/**
 * Record a member opt-out from a notification type via the public unsubscribe flow (P27).
 */
class OptOutMemberCommunication
{
    public function __construct(
        private readonly CommunicationPreferences $preferences,
    ) {}

    public function __invoke(Member $member, string $notificationTypeKey): void
    {
        $this->preferences->optOut($member, $notificationTypeKey);
    }
}
