<?php

declare(strict_types=1);

namespace App\Domain\Crew\Queries;

use App\Domain\Crew\Data\Planning\CandidateMatchCollectionData;
use App\Domain\Crew\Data\Planning\CandidateMatchData;
use App\Domain\Crew\Data\Planning\MatchCrewCandidatesData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Stores\Scopes\StoreScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class CrewCandidateQuery
{
    /** @return Collection<int,CandidateMatchData> */
    public function forLine(CrewRequestLine $line, MatchCrewCandidatesData $filters): Collection
    {
        return collect($this->reportForLine($line, $filters)->candidates);
    }

    public function reportForLine(CrewRequestLine $line, MatchCrewCandidatesData $filters): CandidateMatchCollectionData
    {
        Gate::authorize('view', $line->request);
        Gate::authorize('crew.view');
        Validator::make($filters->toArray(), ['limit' => ['integer', 'min:1', 'max:100'], 'offset' => ['integer', 'min:0', 'max:100000']])->validate();
        $line->loadMissing(['role.requirements', 'requirements', 'request']);
        $members = CrewMember::query()->with(['roles', 'skills', 'qualifications'])->where('status', 'active')->whereHas('roles', function ($query) use ($line): void {
            $query->where('crew_roles.id', $line->crew_role_id);
        })
            ->where(function ($query) use ($line): void {
                $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', $line->effectiveStartsAt()->toDateString());
            })
            ->where(function ($query) use ($line): void {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $line->effectiveEndsAt()->toDateString());
            })->orderBy('name')->orderBy('id')->offset($filters->offset)->limit(501)->get();
        $truncated = $members->count() > 500;
        if ($truncated) {
            Log::warning('Crew candidate pool reached its scoring limit.', ['crew_request_line_id' => $line->id, 'limit' => 500, 'offset' => $filters->offset]);
            $members = $members->take(500);
        }
        $ids = $members->modelKeys();
        $restMinutes = (int) ((float) settings('crew.min_rest_hours', 11) * 60);
        $restEnforcement = settings('crew.rest_enforcement', 'hard');
        $shifts = CrewShift::withoutGlobalScope(StoreScope::class)->whereIn('crew_member_id', $ids)->whereIn('status', ['planned', 'confirmed', 'in_progress', 'completed'])
            ->where('starts_at', '<', $line->effectiveEndsAt()->addMinutes($restMinutes))->where('ends_at', '>', $line->effectiveStartsAt()->subMinutes($restMinutes))->get();
        $schedule = [];
        $busy = collect();
        foreach ($shifts as $shift) {
            $overlap = $shift->starts_at->lt($line->effectiveEndsAt()) && $shift->ends_at->gt($line->effectiveStartsAt());
            if ($overlap) {
                if ($shift->status->value === 'planned') {
                    $schedule[$shift->crew_member_id]['warnings'][] = ['code' => 'SHIFT_OVERLAP_PLANNED', 'message' => 'The member has another planned shift during this period.'];
                } else {
                    $busy->push($shift->crew_member_id);
                }
            } elseif ($shift->status->value !== 'planned' && $restEnforcement !== 'off') {
                $gap = $shift->ends_at->lte($line->effectiveStartsAt()) ? $shift->ends_at->diffInMinutes($line->effectiveStartsAt()) : $line->effectiveEndsAt()->diffInMinutes($shift->starts_at);
                if ($gap < $restMinutes) {
                    $severity = $restEnforcement === 'hard' ? 'hard' : 'warnings';
                    $schedule[$shift->crew_member_id][$severity][] = ['code' => 'REST_BELOW_MINIMUM', 'message' => 'The interval between shifts is below the configured minimum rest.'];
                }
            }
        }
        $blocks = CrewAvailability::query()->whereIn('crew_member_id', $ids)->whereIn('status', ['approved', 'requested'])->where('starts_at', '<', $line->effectiveEndsAt())->where('ends_at', '>', $line->effectiveStartsAt())->get();
        $leave = collect();
        foreach ($blocks as $block) {
            if ($block->status->value === 'approved') {
                $leave->push($block->crew_member_id);
            } else {
                $schedule[$block->crew_member_id]['warnings'][] = ['code' => 'LEAVE_REQUESTED', 'message' => 'The member has requested unavailability during this period.'];
            }
        }
        $booked = CrewAssignment::withoutGlobalScope(StoreScope::class)->whereIn('crew_member_id', $ids)->where('status', 'confirmed')->whereDoesntHave('shifts', function ($query): void {
            $query->withoutGlobalScope(StoreScope::class)->whereIn('status', ['confirmed', 'in_progress', 'completed']);
        })->whereHas('line', function ($query) use ($line): void {
            $query->whereRaw('COALESCE(crew_request_lines.starts_at, (SELECT starts_at FROM crew_requests WHERE crew_requests.id = crew_request_lines.crew_request_id)) < ?', [$line->effectiveEndsAt()])->whereRaw('COALESCE(crew_request_lines.ends_at, (SELECT ends_at FROM crew_requests WHERE crew_requests.id = crew_request_lines.crew_request_id)) > ?', [$line->effectiveStartsAt()]);
        })->pluck('crew_member_id');
        $blocked = $busy->merge($leave)->merge($booked)->unique();

        $matches = $members->map(function (CrewMember $member) use ($line, $blocked, $schedule): CandidateMatchData {
            $requirements = app(CrewRequirementQuery::class)->forLine($member, $line);
            $requirements->hard = [...$requirements->hard, ...($schedule[$member->id]['hard'] ?? [])];
            $requirements->warnings = [...$requirements->warnings, ...($schedule[$member->id]['warnings'] ?? [])];
            if ($blocked->contains($member->id)) {
                $requirements->hard[] = ['code' => 'UNAVAILABLE', 'message' => 'The member has a conflicting commitment.'];
            }
            $role = $member->roles->firstWhere('id', $line->crew_role_id);
            $levels = ['trainee' => 0, 'competent' => 1, 'expert' => 2, 'lead' => 3];
            $score = 50 + ($levels[$role->getRelation('pivot')->getAttribute('proficiency')] ?? 0) * 10 + ($role->getRelation('pivot')->getAttribute('is_primary') ? 10 : 0) - count($requirements->warnings) * 5;

            return new CandidateMatchData($member->id, $member->name, max(0, min(100, $score)), $requirements->hard, $requirements->warnings);
        })->filter(function (CandidateMatchData $candidate) use ($filters): bool {
            return $filters->include_blocked || $candidate->blockers === [];
        })->sort(function (CandidateMatchData $a, CandidateMatchData $b): int {
            return ($b->score <=> $a->score) ?: strcmp($a->name, $b->name);
        })->take($filters->limit)->values();

        return new CandidateMatchCollectionData($matches->all(), $truncated, $filters->offset, $truncated ? $filters->offset + 500 : null);
    }
}
