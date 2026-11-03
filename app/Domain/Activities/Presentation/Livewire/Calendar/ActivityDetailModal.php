<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire\Calendar;

use App\Domain\Activities\Actions\CompleteActivity;
use App\Domain\Activities\Actions\DeleteActivity;
use App\Domain\Activities\Data\ActivityData;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityParticipant;
use App\Domain\Activities\Services\Calendar\OwnerColorResolver;
use App\Domain\Administration\Models\User;
use App\Domain\Attachments\Services\FileService;
use App\Domain\Members\Models\Member;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read ActivityData|null $activity
 * @property-read list<array{name: string, initials: string, color: string, src: string|null}> $participantNames
 * @property-read array{name: string, initials: string, src: string|null, url: string}|null $regardingMember
 */
final class ActivityDetailModal extends Component
{
    public ?int $activityId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('activities.access') ?? false, 403);
    }

    /**
     * Load an activity into the detail view. Named parameter matches the calendar
     * event contract (`calendar-open-detail` dispatched with `{ activityId }`).
     */
    #[On('calendar-open-detail')]
    public function open(?int $activityId = null): void
    {
        $this->activityId = $activityId;
    }

    public function complete(): void
    {
        if ($this->activityId === null) {
            return;
        }

        abort_unless(auth()->user()?->can('activities.complete') ?? false, 403);

        $activity = Activity::findOrFail($this->activityId);
        app(CompleteActivity::class)($activity);

        $this->dispatch('calendar-refresh');
        $this->dispatch('activity-detail-done', message: 'Activity completed');
    }

    public function delete(): void
    {
        if ($this->activityId === null) {
            return;
        }

        abort_unless(auth()->user()?->can('activities.delete') ?? false, 403);

        $activity = Activity::findOrFail($this->activityId);
        app(DeleteActivity::class)($activity);

        $this->reset('activityId');

        $this->dispatch('calendar-refresh');
        $this->dispatch('activity-detail-done', message: 'Activity deleted');
    }

    /**
     * Resolve the activity for display each render (kept out of Livewire state —
     * Spatie Data objects are not serialisable as public properties).
     */
    public function getActivityProperty(): ?ActivityData
    {
        if ($this->activityId === null) {
            return null;
        }

        $activity = Activity::with(['owner.member', 'type', 'regarding', 'participants.member'])->find($this->activityId);

        return $activity ? ActivityData::fromModel($activity) : null;
    }

    /**
     * Participant display names, resolved to staff (User) identity where the
     * participant member is a user's linked member.
     *
     * @return list<array{name: string, initials: string, color: string, src: string|null}>
     */
    public function getParticipantNamesProperty(): array
    {
        if ($this->activityId === null) {
            return [];
        }

        $activity = Activity::with(['participants.member' => fn ($query) => $query->withTrashed()->with('user')])->find($this->activityId);

        if ($activity === null) {
            return [];
        }

        return $activity->participants
            ->map(function (ActivityParticipant $participant): array {
                $member = $participant->getRelation('member');
                $user = $member instanceof Member ? $member->getRelation('user') : null;
                $name = $user instanceof User ? $user->name : ($member instanceof Member ? $member->name : 'Unknown');
                $initials = Str::of($name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('');
                $color = $user instanceof User ? str_replace('s-avatar-', '', app(OwnerColorResolver::class)->for($user->id)) : 'zinc';

                return ['name' => $name, 'initials' => $initials, 'color' => $color, 'src' => app(FileService::class)->signedUrlOrNull($member instanceof Member ? $member->icon_thumb_url : null)];
            })
            ->values()
            ->all();
    }

    /**
     * When the activity's "regarding" is a Member, resolve avatar + link data:
     * the photo (signed thumbnail when present), initials fallback, and the
     * member record URL. Returns null for any other regarding type.
     *
     * @return array{name: string, initials: string, src: string|null, url: string}|null
     */
    public function getRegardingMemberProperty(): ?array
    {
        if ($this->activity?->regarding_type !== 'Member' || $this->activity->regarding_id === null) {
            return null;
        }

        $member = Member::find($this->activity->regarding_id);

        if ($member === null) {
            return null;
        }

        $name = (string) $member->name;
        $initials = Str::of($name)->explode(' ')->take(2)
            ->map(fn ($w) => Str::substr($w, 0, 1))->implode('');

        return [
            'name' => $name,
            'initials' => $initials,
            'src' => app(FileService::class)->signedUrlOrNull($member->icon_thumb_url),
            'url' => route('members.show', $member->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'activity' => $this->activity,
            'participantNames' => $this->participantNames,
            'ownerSrc' => $this->activity?->owner_thumb_url,
            'regardingMember' => $this->regardingMember,
            'canComplete' => auth()->user()?->can('activities.complete') ?? false,
            'canDelete' => auth()->user()?->can('activities.delete') ?? false,
            'canEdit' => auth()->user()?->can('activities.edit') ?? false,
        ];
    }

    public function render(): View
    {
        return view('activities::calendar.activity-detail-modal', $this->with());
    }
}
