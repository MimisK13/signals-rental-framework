<?php

declare(strict_types=1);

namespace App\Domain\Crew\Console;

use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewMemberAnonymizer;
use Illuminate\Console\Command;

final class AnonymiseExpiredCrewContacts extends Command
{
    protected $signature = 'crew:anonymise-expired-contacts';

    protected $description = 'Anonymise archived Crew contacts after the configured inactivity retention period';

    public function handle(CrewMemberAnonymizer $anonymizer): int
    {
        if (! settings()->moduleEnabled('crew')) {
            return self::SUCCESS;
        }
        $cutoff = now()->subMonthsNoOverflow((int) settings('crew.pii_retention_months', 24));
        CrewMember::onlyTrashed()->where('status', 'archived')->where('deleted_at', '<=', $cutoff)
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('last_assigned_at')->orWhere('last_assigned_at', '<=', $cutoff);
            })
            ->lazyById(100)->each(function (CrewMember $member) use ($anonymizer): void {
                if ($member->name !== 'Archived crew #'.$member->id || $member->email !== null || $member->phone !== null || $member->internal_notes !== null || $member->home_postcode !== null || $member->user_id !== null || $member->member_id !== null || $member->supplier_member_id !== null || $member->external_reference !== null) {
                    $anonymizer->anonymise($member);
                }
            });

        return self::SUCCESS;
    }
}
