<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire;

use App\Domain\Activities\Actions\CompleteActivity;
use App\Domain\Activities\Actions\DeleteActivity;
use App\Domain\Activities\Models\Activity;
use App\Domain\Attachments\Presentation\Concerns\HasFileActions;
use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use App\Livewire\Concerns\HasAuditTimeline;
use App\Models\Member;
use App\Services\FileService;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * @property-read array{name: string, initials: string, src: string|null, url: string}|null $regardingMember
 */
#[Layout('components.layouts.app')]
final class Show extends Component
{
    use HasAuditTimeline;
    use HasFileActions;

    public Activity $activity;

    public ?int $deleteAttachmentId = null;

    public function mount(Activity $activity): void
    {
        $this->activity = $activity->load([
            'owner.member',
            'type',
            'regarding',
            'participants.member' => fn ($query) => $query->withTrashed()->with('user'),
        ])->loadCount('attachments');
    }

    public function rendering(View $view): void
    {
        $view->title($this->activity->subject);
    }

    public function completeActivity(): void
    {
        (new CompleteActivity)($this->activity);
        $this->activity->refresh();
    }

    public function deleteActivity(): void
    {
        (new DeleteActivity)($this->activity);
        $this->redirect(route('activities.index'), navigate: true);
    }

    protected function getFileableModel(): Activity
    {
        return $this->activity;
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
        if (Activity::shortRegardingType($this->activity->regarding_type) !== 'Member' || $this->activity->regarding_id === null) {
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
     * Activity-specific timeline colours: completions read as positive (green),
     * cancellations as destructive (red).
     *
     * @return array<string, list<string>>
     */
    protected function timelineColorMap(): array
    {
        return [
            'green' => ['.created', '.restored', '.completed'],
            'red' => ['.deleted', '.cancelled'],
            'blue' => ['.updated'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $fields = CustomField::query()
            ->forModule('Activity')
            ->active()
            ->with('group')
            ->orderBy('sort_order')
            ->get();

        $values = $this->activity->customFieldValues()
            ->with('customField')
            ->get()
            ->keyBy('custom_field_id');

        return [
            'regardingMember' => $this->regardingMember,
            'timeline' => $this->auditTimelineFor($this->activity),
            'groupedCustomFields' => $fields->groupBy(function (CustomField $field): string {
                $group = $field->getRelation('group');

                return $group instanceof CustomFieldGroup ? $group->name : 'General';
            }),
            'customFieldValues' => $values,
            ...$this->fileData(),
        ];
    }

    public function render(): View
    {
        return view('activities::show', $this->with());
    }
}
