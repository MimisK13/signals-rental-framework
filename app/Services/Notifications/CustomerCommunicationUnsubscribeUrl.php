<?php

namespace App\Services\Notifications;

use App\Models\Member;
use Illuminate\Support\Facades\URL;

/**
 * Generates signed unsubscribe URLs scoped to a member and notification type (P27).
 */
class CustomerCommunicationUnsubscribeUrl
{
    public function forMember(Member $member, string $notificationTypeKey): string
    {
        $ttlDays = max(1, (int) settings('notifications.unsubscribe_url_ttl_days', 365));

        return URL::temporarySignedRoute(
            'communications.unsubscribe.show',
            now()->addDays($ttlDays),
            [
                'member' => $member->id,
                'type' => $notificationTypeKey,
            ],
        );
    }
}
