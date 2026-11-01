<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire\Members;

use App\Domain\Crew\Actions\Registry\CreateCrewMember;
use App\Domain\Crew\Actions\Registry\UpdateCrewMember;
use App\Domain\Crew\Data\Registry\CreateCrewMemberData;
use App\Domain\Crew\Data\Registry\UpdateCrewMemberData;
use App\Domain\Crew\Enums\CrewEngagementType;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Presentation\Livewire\CrewComponent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Crew member')]
final class Form extends CrewComponent
{
    #[Locked]
    public ?int $memberId = null;

    public string $name = '';

    public string $engagement_type = 'employee';

    public string $home_timezone = 'UTC';

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $job_title = null;

    public function mount(?CrewMember $crewMember = null): void
    {
        if ($crewMember?->exists) {
            Gate::authorize('update', $crewMember);
            $this->memberId = $crewMember->id;
            $this->fill($crewMember->only(['name', 'home_timezone', 'job_title']));
            $this->engagement_type = $crewMember->engagement_type->value;
            if (Gate::allows('viewSensitive', $crewMember)) {
                $this->fill($crewMember->only(['email', 'phone']));
            }
        } else {
            Gate::authorize('create', CrewMember::class);
        }
    }

    public function save(): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:255'], 'engagement_type' => ['required', Rule::enum(CrewEngagementType::class)], 'home_timezone' => ['required', 'timezone'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'job_title' => ['nullable', 'string', 'max:255']]);
        $this->performCrewAction(function () use ($data): void {
            if ($this->memberId) {
                $member = CrewMember::findOrFail($this->memberId);
                if (! Gate::allows('viewSensitive', $member)) {
                    $data['email'] = $member->email;
                    $data['phone'] = $member->phone;
                }
                $data += $member->only(['member_id', 'user_id', 'supplier_member_id', 'internal_notes']);
                $data['starts_on'] = $member->starts_on?->toDateString();
                $data['ends_on'] = $member->ends_on?->toDateString();
                $saved = app(UpdateCrewMember::class)($member, UpdateCrewMemberData::from($data));
            } else {
                $saved = app(CreateCrewMember::class)(CreateCrewMemberData::from($data));
            }
            $this->redirectRoute('crew.members.show', ['crewMember' => $saved->id], navigate: true);
        });
    }

    public function render(): View
    {
        return view('crew::members.form');
    }
}
