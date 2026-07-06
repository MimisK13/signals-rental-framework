<?php

namespace App\Services\Notifications;

use App\Models\Member;

/**
 * Read and update member communication opt-out preferences (P27).
 */
class CommunicationPreferences
{
    /**
     * @return list<string>
     */
    public function optedOutTypes(Member $member): array
    {
        /** @var array<string, mixed>|null $preferences */
        $preferences = $member->communication_preferences;

        if ($preferences === null) {
            return [];
        }

        $optOut = $preferences['opt_out'] ?? [];

        if (! is_array($optOut)) {
            return [];
        }

        return array_values(array_filter($optOut, is_string(...)));
    }

    public function isOptedOut(Member $member, string $notificationTypeKey): bool
    {
        return in_array($notificationTypeKey, $this->optedOutTypes($member), true);
    }

    public function optOut(Member $member, string $notificationTypeKey): void
    {
        $preferences = $member->communication_preferences ?? [];
        $optOut = $this->optedOutTypes($member);

        if (! in_array($notificationTypeKey, $optOut, true)) {
            $optOut[] = $notificationTypeKey;
        }

        $preferences['opt_out'] = $optOut;

        $member->update(['communication_preferences' => $preferences]);
    }

    public function maskedEmail(Member $member): ?string
    {
        $email = $member->routeNotificationForMail();

        if ($email === null || $email === '') {
            return null;
        }

        if (! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        if ($local === '') {
            return '*@'.$domain;
        }

        $visible = mb_substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }
}
