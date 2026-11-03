<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Presentation\Livewire;

use App\Domain\API\Services\RansackFilter;
use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Member;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityParticipant;
use App\Domain\Projects\Opportunities\Actions\RemoveOpportunityParticipant;
use App\Domain\Projects\Opportunities\Actions\UpdateOpportunityParticipant;
use App\Domain\Projects\Opportunities\Data\AddOpportunityParticipantData;
use App\Domain\Projects\Opportunities\Data\UpdateOpportunityParticipantData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityParticipant;
use App\Domain\Projects\Opportunities\Presentation\Concerns\HasOpportunityActions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Manage the members associated with an opportunity, each in a named role
 * (the RMS `participants[]` shape — C3f).
 *
 * Participants are plain, non-event-sourced CRM associations. The panel lets an
 * editor attach a member (organisation or contact), pick a role from the
 * suggested set (free-text-backed), toggle mute, and remove participants. Every
 * mutation routes through the same action classes the API uses, so the
 * business-logic path is shared.
 */
#[Layout('components.layouts.app')] final class Participants extends Component
{
    use HasOpportunityActions;

    /**
     * The free-text roles the UI suggests (the column is a plain string, so any
     * value is accepted by the action).
     *
     * @var list<string>
     */
    public const SUGGESTED_ROLES = [
        'Primary contact',
        'Secondary contact',
        'Account manager',
        'Site contact',
    ];

    public Opportunity $opportunity;

    public string $memberSearch = '';

    public ?int $memberId = null;

    public ?string $memberSelectedName = null;

    public string $role = '';

    public bool $mute = false;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Participants');
    }

    /**
     * Search organisation/contact members not already attached, for the picker.
     *
     * @return Collection<int, Member>
     */
    public function getMemberOptionsProperty(): Collection
    {
        if (trim($this->memberSearch) === '') {
            return collect();
        }

        $like = (string) config('database.connections.'.config('database.default').'.driver') === 'pgsql' ? 'ilike' : 'like';

        return Member::query()
            ->where('is_active', true)
            ->whereIn('membership_type', [MembershipType::Organisation, MembershipType::Contact])
            ->whereNotIn('id', $this->opportunity->participants()->pluck('member_id'))
            ->where('name', $like, '%'.RansackFilter::escapeLike($this->memberSearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'membership_type']);
    }

    /**
     * The opportunity's current participants, member eager-loaded.
     *
     * @return Collection<int, OpportunityParticipant>
     */
    public function getParticipantsProperty(): Collection
    {
        return $this->opportunity->participants()->with('member')->get();
    }

    public function selectMember(int $id): void
    {
        $member = Member::query()
            ->where('is_active', true)
            ->whereIn('membership_type', [MembershipType::Organisation, MembershipType::Contact])
            ->find($id);

        if ($member === null) {
            return;
        }

        $this->memberId = $member->id;
        $this->memberSelectedName = $member->name;
        $this->memberSearch = '';
    }

    public function clearMember(): void
    {
        $this->memberId = null;
        $this->memberSelectedName = null;
        $this->memberSearch = '';
    }

    public function add(): void
    {
        Gate::authorize('opportunities.edit');

        if ($this->memberId === null) {
            throw ValidationException::withMessages([
                'memberId' => 'Select a member to add.',
            ]);
        }

        $data = AddOpportunityParticipantData::from([
            'member_id' => $this->memberId,
            'role' => trim($this->role) === '' ? null : trim($this->role),
            'mute' => $this->mute,
        ]);

        (new AddOpportunityParticipant)($this->opportunity, $data);

        $this->reset(['memberId', 'memberSelectedName', 'memberSearch', 'role', 'mute']);
    }

    public function updateRole(int $participantId, string $role): void
    {
        $participant = $this->resolveParticipant($participantId);

        (new UpdateOpportunityParticipant)(
            $participant,
            UpdateOpportunityParticipantData::from(['role' => trim($role) === '' ? null : trim($role)]),
        );
    }

    public function toggleMute(int $participantId): void
    {
        $participant = $this->resolveParticipant($participantId);

        (new UpdateOpportunityParticipant)(
            $participant,
            UpdateOpportunityParticipantData::from(['mute' => ! $participant->mute]),
        );
    }

    public function remove(int $participantId): void
    {
        $participant = $this->resolveParticipant($participantId);

        (new RemoveOpportunityParticipant)($participant);
    }

    private function resolveParticipant(int $participantId): OpportunityParticipant
    {
        return $this->opportunity->participants()->findOrFail($participantId);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return $this->opportunityActionData();
    }

    public function render(): View
    {
        return view('project-opportunities::participants');
    }
}
