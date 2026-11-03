<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Members\Actions\CreateMemberRelationship;
use App\Domain\Members\Data\CreateMemberRelationshipData;
use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberRelationship;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class RelationshipForm extends Component
{
    public Member $member;

    public ?int $relatedMemberId = null;

    public string $relationshipType = '';

    public bool $isPrimary = false;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
    }

    public function save(): void
    {
        $this->validate([
            'relatedMemberId' => ['required', 'integer', 'exists:members,id'],
            'relationshipType' => ['nullable', 'string', 'max:255'],
            'isPrimary' => ['boolean'],
        ]);

        $isContact = $this->member->membership_type === MembershipType::Contact;

        if ($isContact) {
            $ownerMember = $this->member;
            $relatedMemberId = $this->relatedMemberId;
        } else {
            $ownerMember = Member::findOrFail($this->relatedMemberId);
            $relatedMemberId = $this->member->id;
        }

        DB::transaction(function () use ($isContact, $ownerMember, $relatedMemberId) {
            if ($this->isPrimary && $isContact) {
                MemberRelationship::query()
                    ->where('member_id', $this->member->id)
                    ->update(['is_primary' => false]);
            }

            (new CreateMemberRelationship)($ownerMember, CreateMemberRelationshipData::from([
                'related_member_id' => $relatedMemberId,
                'relationship_type' => $this->relationshipType ?: null,
                'is_primary' => $this->isPrimary,
            ]));
        });

        $this->redirect(route('members.show', $this->member), navigate: true);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $isContact = $this->member->membership_type === MembershipType::Contact;

        // Allowed pairs (matrix): contact↔organisation, contact↔venue,
        // contact↔contact. Organisations/venues can only link to contacts.
        // User-type members never appear as eligible counterparts.
        if ($isContact) {
            $eligibleTypes = [
                MembershipType::Organisation,
                MembershipType::Venue,
                MembershipType::Contact,
            ];
        } else {
            $eligibleTypes = [MembershipType::Contact];
        }

        $eligibleMembers = Member::query()
            ->whereIn('membership_type', $eligibleTypes)
            ->where('is_active', true)
            ->where('id', '!=', $this->member->id)
            ->orderBy('name')
            ->get();

        return [
            'isContact' => $isContact,
            'eligibleMembers' => $eligibleMembers,
        ];
    }

    public function render(): View
    {
        return view('members::relationship-form', $this->with());
    }
}
