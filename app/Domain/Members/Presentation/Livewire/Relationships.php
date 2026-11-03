<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Members\Actions\DeleteMemberRelationship;
use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberRelationship;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class Relationships extends Component
{
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function deleteRelationship(int $relationshipId): void
    {
        $relationship = MemberRelationship::query()
            ->where(function ($query) use ($relationshipId) {
                $query->where('id', $relationshipId)
                    ->where(function ($q) {
                        $q->where('member_id', $this->member->id)
                            ->orWhere('related_member_id', $this->member->id);
                    });
            })
            ->firstOrFail();

        (new DeleteMemberRelationship)($relationship);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $isContact = $this->member->membership_type === MembershipType::Contact;

        // Relationships are bidirectional: union both directions so the same
        // link appears on each member regardless of how it was created.
        $relationships = $this->member->allRelationships();

        return [
            'relationships' => $relationships,
            'isContact' => $isContact,
        ];
    }

    public function render(): View
    {
        return view('members::relationships', $this->with());
    }
}
