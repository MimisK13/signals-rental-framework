<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire\Requests;

use App\Domain\Crew\Actions\Assignments\ProposeCrewAssignment;
use App\Domain\Crew\Actions\Requests\AddCrewRequestLine;
use App\Domain\Crew\Actions\Requests\CancelCrewRequest;
use App\Domain\Crew\Actions\Requests\PublishCrewRequest;
use App\Domain\Crew\Data\Assignments\ProposeCrewAssignmentData;
use App\Domain\Crew\Data\Planning\CandidateMatchData;
use App\Domain\Crew\Data\Planning\MatchCrewCandidatesData;
use App\Domain\Crew\Data\Requests\AddCrewRequestLineData;
use App\Domain\Crew\Data\Requests\CancelCrewRequestData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Presentation\Livewire\CrewComponent;
use App\Domain\Crew\Queries\CrewCandidateQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

/**
 * @property-read CrewRequest $request
 * @property-read \Illuminate\Support\Collection<int, CandidateMatchData> $candidates
 * @property-read Collection<int, CrewRole> $roles
 * @property-read Collection<int, CrewMember> $members
 * @property-read Collection<int, CrewAssignment> $assignments
 */
#[Layout('components.layouts.app')]
#[Title('Crew request')]
final class Show extends CrewComponent
{
    #[Locked]
    public int $requestId;

    public ?int $crew_role_id = null;

    public int $quantity = 1;

    public ?int $line_id = null;

    public ?int $crew_member_id = null;

    public string $currency_code = 'GBP';

    public string $cancellation_reason = '';

    public ?string $override_reason = null;

    /** @return \Illuminate\Support\Collection<int, CandidateMatchData> */
    #[Computed]
    public function candidates(): \Illuminate\Support\Collection
    {
        if ($this->line_id === null) {
            return collect();
        }

        return app(CrewCandidateQuery::class)->forLine(
            $this->request->lines()->findOrFail($this->line_id),
            new MatchCrewCandidatesData(include_blocked: true),
        );
    }

    public function mount(CrewRequest $crewRequest): void
    {
        Gate::authorize('view', $crewRequest);
        $this->requestId = $crewRequest->id;
    }

    #[Computed]
    public function request(): CrewRequest
    {
        $request = CrewRequest::with(['project', 'lines.role'])->findOrFail($this->requestId);
        Gate::authorize('view', $request);

        return $request;
    }

    /** @return Collection<int, CrewRole> */
    #[Computed]
    public function roles(): Collection
    {
        return CrewRole::where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, CrewMember> */
    #[Computed]
    public function members(): Collection
    {
        Gate::authorize('crew.assignments.create');

        return CrewMember::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, CrewAssignment> */
    #[Computed]
    public function assignments(): Collection
    {
        Gate::authorize('crew.assignments.view');

        return $this->request->assignments()->with(['crewMember', 'line.role'])->get();
    }

    public function publish(): void
    {
        $this->performCrewAction(function (): void {
            app(PublishCrewRequest::class)($this->request);
            unset($this->request);
        });
    }

    public function addLine(): void
    {
        $this->validate(['crew_role_id' => ['required', 'integer', 'exists:crew_roles,id'], 'quantity' => ['required', 'integer', 'min:1']]);
        $this->performCrewAction(function (): void {
            app(AddCrewRequestLine::class)($this->request, AddCrewRequestLineData::from(['crew_role_id' => $this->crew_role_id, 'quantity' => $this->quantity, 'starts_at' => $this->request->starts_at->toIso8601String(), 'ends_at' => $this->request->ends_at->toIso8601String()]));
            unset($this->request);
        });
    }

    public function propose(): void
    {
        $this->validate(['line_id' => ['required', 'integer'], 'crew_member_id' => ['required', 'integer', 'exists:crew_members,id'], 'currency_code' => ['required', 'string', 'size:3']]);
        $this->performCrewAction(function (): void {
            $assignment = app(ProposeCrewAssignment::class)($this->request->lines()->findOrFail($this->line_id), ProposeCrewAssignmentData::from(['crew_member_id' => $this->crew_member_id, 'currency_code' => $this->currency_code]), $this->override_reason);
            $this->redirectRoute('crew.assignments.show', ['crewAssignment' => $assignment->id], navigate: true);
        });
    }

    public function cancel(): void
    {
        $this->validate(['cancellation_reason' => ['required', 'string', 'max:1000']]);
        $this->performCrewAction(function (): void {
            app(CancelCrewRequest::class)($this->request, CancelCrewRequestData::from(['reason' => $this->cancellation_reason]));
            unset($this->request);
        });
    }

    public function render(): View
    {
        return view('crew::requests.show');
    }
}
