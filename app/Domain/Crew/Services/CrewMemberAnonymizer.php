<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\Crew\Models\CrewMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CrewMemberAnonymizer
{
    public function anonymise(CrewMember $member): void
    {
        DB::transaction(function () use ($member): void {
            $member = CrewMember::withTrashed()->lockForUpdate()->findOrFail($member->id);
            if (! $member->trashed() || $member->status->value !== 'archived') {
                throw ValidationException::withMessages(['crew_member' => 'Archive the crew member before anonymising contact data.']);
            }
            $member->update([
                'name' => 'Archived crew #'.$member->id, 'email' => null, 'phone' => null,
                'home_postcode' => null, 'internal_notes' => null, 'external_reference' => null,
                'member_id' => null, 'user_id' => null, 'supplier_member_id' => null,
            ]);
            CrewAudit::record('crew.member.anonymised', $member);
        });
    }
}
