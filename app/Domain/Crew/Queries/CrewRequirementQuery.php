<?php

declare(strict_types=1);

namespace App\Domain\Crew\Queries;

use App\Domain\Crew\Data\Planning\CrewConflictReportData;
use App\Domain\Crew\Enums\CrewProficiency;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewRoleRequirement;
use Carbon\CarbonImmutable;

final class CrewRequirementQuery
{
    public function forLine(CrewMember $member, CrewRequestLine $line): CrewConflictReportData
    {
        $member->loadMissing(['roles', 'skills', 'qualifications']);
        $line->loadMissing(['role.requirements', 'requirements', 'request']);
        $report = new CrewConflictReportData;
        if (! $line->role->is_active) {
            $report->hard[] = ['code' => 'ROLE_INACTIVE', 'message' => 'The requested role is inactive.'];
        }
        if (! $member->roles->contains('id', $line->crew_role_id)) {
            $report->warnings[] = ['code' => 'ROLE_NOT_HELD', 'message' => 'The member does not hold the requested role.'];
        }
        $levels = ['trainee' => 0, 'competent' => 1, 'expert' => 2, 'lead' => 3];
        $heldRole = $member->roles->firstWhere('id', $line->crew_role_id);
        if ($heldRole && $line->min_proficiency && ($levels[$heldRole->getRelation('pivot')->getAttribute('proficiency')] ?? -1) < $levels[$line->min_proficiency->value]) {
            $report->hard[] = ['code' => 'ROLE_BELOW_MINIMUM', 'message' => 'Role proficiency is below the required minimum.'];
        }
        foreach ($line->role->requirements->concat($line->requirements) as $requirement) {
            $issue = null;
            if ($requirement->crew_skill_id) {
                $skill = $member->skills->firstWhere('id', $requirement->crew_skill_id);
                if (! $skill) {
                    $issue = ['code' => 'SKILL_MISSING_MANDATORY', 'message' => 'A required skill is missing.'];
                } elseif (($levels[$skill->getRelation('pivot')->getAttribute('proficiency')] ?? -1) < $levels[($requirement->min_proficiency ?? CrewProficiency::Trainee)->value]) {
                    $issue = ['code' => 'SKILL_BELOW_MINIMUM', 'message' => 'A skill is below the required proficiency.'];
                } elseif ($requirement instanceof CrewRoleRequirement && $requirement->min_years_experience !== null && (float) $skill->getRelation('pivot')->getAttribute('years_experience') < $requirement->min_years_experience) {
                    $issue = ['code' => 'SKILL_EXPERIENCE', 'message' => 'Skill experience is below the required minimum.'];
                }
            } else {
                $qualification = $member->qualifications->firstWhere('crew_qualification_id', $requirement->crew_qualification_id);
                if (! $qualification || ! $qualification->verified_at) {
                    $issue = ['code' => 'QUALIFICATION_MISSING', 'message' => 'A verified qualification is required.'];
                } elseif ($qualification->expires_at && $qualification->expires_at->toDateString() < $line->effectiveEndsAt()->subSecond()->timezone($line->request->timezone)->toDateString()) {
                    $issue = ['code' => 'QUALIFICATION_EXPIRED', 'message' => 'A qualification expires before this engagement ends.'];
                } elseif ($qualification->expires_at && $qualification->expires_at->lte(CarbonImmutable::parse($line->effectiveEndsAt())->addDays((int) settings('crew.qualification_expiry_warning_days', 30)))) {
                    $report->warnings[] = ['code' => 'QUALIFICATION_EXPIRING', 'message' => 'A qualification is approaching expiry.'];
                }
            }
            if ($issue) {
                if ($requirement->is_mandatory) {
                    $report->hard[] = $issue;
                } else {
                    $report->warnings[] = $issue;
                }
            }
        }

        return $report;
    }
}
