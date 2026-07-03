<?php

namespace App\Services\Notifications\RecipientResolvers;

use App\Contracts\Notifications\RecipientResolver;
use App\Data\Notifications\NotificationContextData;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\ShortageWaitlistMonitor;
use Illuminate\Support\Collection;

/**
 * Resolves the assigned owner (owned_by) from the notification subject.
 */
class SubjectOwnerRecipientResolver implements RecipientResolver
{
    public function type(): string
    {
        return 'subject_owner';
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return Collection<int, Member>
     */
    public function resolve(array $rule, NotificationContextData $context): Collection
    {
        $subject = $context->subject;

        $ownerId = match (true) {
            $subject instanceof Opportunity => $subject->owned_by,
            $subject instanceof ShortageWaitlistMonitor => $this->ownerIdForWaitlistMonitor($subject),
            isset($subject->owned_by) => $subject->owned_by,
            default => null,
        };

        if ($ownerId === null) {
            return collect();
        }

        return Member::query()->whereKey($ownerId)->get();
    }

    private function ownerIdForWaitlistMonitor(ShortageWaitlistMonitor $monitor): ?int
    {
        if ($monitor->opportunity_item_id === null) {
            return null;
        }

        $opportunityId = OpportunityItem::query()
            ->whereKey($monitor->opportunity_item_id)
            ->value('opportunity_id');

        if ($opportunityId === null) {
            return null;
        }

        $ownerId = Opportunity::query()->whereKey($opportunityId)->value('owned_by');

        return $ownerId !== null ? (int) $ownerId : null;
    }
}
