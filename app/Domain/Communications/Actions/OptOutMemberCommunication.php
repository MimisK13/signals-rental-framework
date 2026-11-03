<?php

declare(strict_types=1);

namespace App\Domain\Communications\Actions;

use App\Domain\Communications\Services\CommunicationPreferences;
use App\Domain\Members\Models\Member;

/**
 * Record a member opt-out from a notification type via the public unsubscribe flow (P27).
 */
final class OptOutMemberCommunication
{
    public function __construct(
        private readonly CommunicationPreferences $preferences,
    ) {}

    public function __invoke(Member $member, string $notificationTypeKey): void
    {
        $this->preferences->optOut($member, $notificationTypeKey);
    }
}
