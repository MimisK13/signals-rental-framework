<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\Crew\Enums\CrewEngagementType;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Member;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CrewMemberValidation
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input, ?CrewMember $member = null): array
    {
        $input['name'] = trim($input['name']);
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:160'],
            'engagement_type' => ['required', Rule::enum(CrewEngagementType::class)],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:60'],
            'home_timezone' => ['required', 'timezone'], 'job_title' => ['nullable', 'string', 'max:120'],
            'internal_notes' => ['nullable', 'string', 'max:20000'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('crew_members')->ignore($member?->id)],
            'supplier_member_id' => ['nullable', 'integer', 'exists:members,id'],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ])->validate();
        if ($validated['member_id'] !== null && ! in_array(Member::findOrFail($validated['member_id'])->membership_type, [MembershipType::Contact, MembershipType::User], true)) {
            throw ValidationException::withMessages(['member_id' => 'Crew identity must be a person.']);
        }
        if ($validated['supplier_member_id'] !== null && Member::findOrFail($validated['supplier_member_id'])->membership_type !== MembershipType::Organisation) {
            throw ValidationException::withMessages(['supplier_member_id' => 'The supplier must be an organisation.']);
        }

        return $validated;
    }
}
