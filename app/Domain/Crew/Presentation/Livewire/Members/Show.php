<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire\Members;

use App\Domain\Crew\Actions\Availability\ApproveCrewAvailability;
use App\Domain\Crew\Actions\Availability\RequestCrewAvailability;
use App\Domain\Crew\Actions\Registry\AddCrewMemberQualification;
use App\Domain\Crew\Actions\Registry\SyncCrewMemberRoles;
use App\Domain\Crew\Actions\Registry\SyncCrewMemberSkills;
use App\Domain\Crew\Actions\Registry\VerifyCrewMemberQualification;
use App\Domain\Crew\Data\Availability\RequestCrewAvailabilityData;
use App\Domain\Crew\Data\Registry\AddCrewMemberQualificationData;
use App\Domain\Crew\Data\Registry\SyncCrewMemberRolesData;
use App\Domain\Crew\Data\Registry\SyncCrewMemberSkillsData;
use App\Domain\Crew\Enums\CrewProficiency;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewQualification;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Models\CrewSkill;
use App\Domain\Crew\Presentation\Livewire\CrewComponent;
use App\Support\Timezone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

/**
 * @property-read CrewMember $member
 * @property-read Collection<int, CrewSkill> $skills
 * @property-read Collection<int, CrewRole> $roles
 * @property-read Collection<int, CrewQualification> $qualificationTypes
 * @property-read Collection<int, CrewAssignment> $assignments
 * @property-read Collection<int, CrewAvailability> $availability
 */
#[Layout('components.layouts.app')]
#[Title('Crew member')]
final class Show extends CrewComponent
{
    #[Locked]
    public int $memberId;

    public ?int $skill_id = null;

    public ?int $role_id = null;

    public bool $is_primary = true;

    public ?int $crew_qualification_id = null;

    public ?string $issued_at = null;

    public ?string $expires_at = null;

    public ?string $reference_number = null;

    public string $proficiency = 'competent';

    public string $starts_at = '';

    public string $ends_at = '';

    public ?string $reason = null;

    public function mount(CrewMember $crewMember): void
    {
        Gate::authorize('view', $crewMember);
        $this->memberId = $crewMember->id;
    }

    #[Computed]
    public function member(): CrewMember
    {
        $member = CrewMember::with(['roles', 'skills', 'qualifications.qualification'])->findOrFail($this->memberId);
        Gate::authorize('view', $member);

        return $member;
    }

    /** @return Collection<int, CrewSkill> */
    #[Computed]
    public function skills(): Collection
    {
        return CrewSkill::orderBy('name')->get();
    }

    /** @return Collection<int, CrewRole> */
    #[Computed]
    public function roles(): Collection
    {
        return CrewRole::where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, CrewQualification> */
    #[Computed]
    public function qualificationTypes(): Collection
    {
        return CrewQualification::where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, CrewAssignment> */
    #[Computed]
    public function assignments(): Collection
    {
        Gate::authorize('crew.assignments.view');

        return $this->member->assignments()->with('project')->latest()->limit(25)->get();
    }

    /** @return Collection<int, CrewAvailability> */
    #[Computed]
    public function availability(): Collection
    {
        Gate::authorize('crew.availability.view');

        return $this->member->availabilities()->where('ends_at', '>=', now())->orderBy('starts_at')->limit(25)->get();
    }

    public function addSkill(): void
    {
        Gate::authorize('update', $this->member);
        $this->validate(['skill_id' => ['required', 'integer', 'exists:crew_skills,id'], 'proficiency' => ['required', Rule::enum(CrewProficiency::class)]]);
        $this->performCrewAction(function (): void {
            $skills = $this->member->skills->reject(function ($skill): bool {
                return $skill->id === $this->skill_id;
            })->map(function ($skill): array {
                return ['crew_skill_id' => $skill->id, 'proficiency' => $skill->getRelation('pivot')->getAttribute('proficiency'), 'years_experience' => $skill->getRelation('pivot')->getAttribute('years_experience')];
            })->values()->all();
            $skills[] = ['crew_skill_id' => $this->skill_id, 'proficiency' => $this->proficiency];
            app(SyncCrewMemberSkills::class)($this->member, SyncCrewMemberSkillsData::from(['skills' => $skills]));
            unset($this->member);
        });
    }

    public function requestAvailability(): void
    {
        $this->validate(['starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $this->performCrewAction(function (): void {
            [$from, $to] = $this->crewRangeToUtc($this->starts_at, $this->ends_at);
            app(RequestCrewAvailability::class)($this->member, RequestCrewAvailabilityData::from(['starts_at' => $from, 'ends_at' => $to, 'timezone' => app(Timezone::class)->current(), 'reason' => $this->reason]));
            unset($this->availability);
        });
    }

    public function approveAvailability(int $id): void
    {
        $this->performCrewAction(function () use ($id): void {
            app(ApproveCrewAvailability::class)($this->member->availabilities()->findOrFail($id));
            unset($this->availability);
        });
    }

    public function addRole(): void
    {
        Gate::authorize('update', $this->member);
        $this->validate(['role_id' => ['required', 'integer', 'exists:crew_roles,id'], 'proficiency' => ['required', Rule::enum(CrewProficiency::class)], 'is_primary' => ['boolean']]);
        $this->performCrewAction(function (): void {
            $roles = $this->member->roles->reject(function ($role): bool {
                return $role->id === $this->role_id;
            })->map(function ($role): array {
                return ['crew_role_id' => $role->id, 'proficiency' => $role->getRelation('pivot')->getAttribute('proficiency'), 'years_experience' => $role->getRelation('pivot')->getAttribute('years_experience'), 'is_primary' => $this->is_primary ? false : (bool) $role->getRelation('pivot')->getAttribute('is_primary')];
            })->values()->all();
            $roles[] = ['crew_role_id' => $this->role_id, 'proficiency' => $this->proficiency, 'is_primary' => $this->is_primary];
            app(SyncCrewMemberRoles::class)($this->member, SyncCrewMemberRolesData::from(['roles' => $roles]));
            unset($this->member);
        });
    }

    public function addQualification(): void
    {
        $data = $this->validate(['crew_qualification_id' => ['required', 'integer', 'exists:crew_qualifications,id'], 'issued_at' => ['nullable', 'date_format:Y-m-d'], 'expires_at' => ['nullable', 'date_format:Y-m-d'], 'reference_number' => ['nullable', 'string', 'max:100']]);
        $this->performCrewAction(function () use ($data): void {
            app(AddCrewMemberQualification::class)($this->member, AddCrewMemberQualificationData::from($data));
            unset($this->member);
        });
    }

    public function verifyQualification(int $id): void
    {
        $this->performCrewAction(function () use ($id): void {
            app(VerifyCrewMemberQualification::class)($this->member->qualifications()->findOrFail($id));
            unset($this->member);
        });
    }

    public function render(): View
    {
        return view('crew::members.show');
    }
}
