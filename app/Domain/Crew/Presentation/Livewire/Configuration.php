<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire;

use App\Domain\Crew\Actions\Registry\SyncCrewRoleRequirements;
use App\Domain\Crew\Data\Registry\SyncRoleRequirementsData;
use App\Domain\Crew\Models\CrewQualification;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Models\CrewRoleRequirement;
use App\Domain\Crew\Models\CrewSkill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CrewSkill> $requirementSkills
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CrewQualification> $requirementQualifications
 * @property-read Collection<int, CrewRoleRequirement> $requirements
 * @property-read LengthAwarePaginator<int, CrewRole|CrewSkill|CrewQualification> $records
 */
#[Layout('components.layouts.app')]
#[Title('Crew configuration')]
final class Configuration extends CrewComponent
{
    use WithPagination;

    #[Locked]
    public string $kind = 'roles';

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public ?string $description = null;

    public string $category = 'general';

    public ?string $issuer = null;

    public bool $requires_expiry = true;

    public bool $is_active = true;

    public string $requirement_type = 'skill';

    public ?int $requirement_skill_id = null;

    public ?int $requirement_qualification_id = null;

    public bool $is_mandatory = true;

    /** @return \Illuminate\Database\Eloquent\Collection<int, CrewSkill> */
    #[Computed]
    public function requirementSkills(): \Illuminate\Database\Eloquent\Collection
    {
        Gate::authorize('crew.settings.manage');

        return CrewSkill::where('is_active', true)->orderBy('name')->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, CrewQualification> */
    #[Computed]
    public function requirementQualifications(): \Illuminate\Database\Eloquent\Collection
    {
        Gate::authorize('crew.settings.manage');

        return CrewQualification::where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, CrewRoleRequirement> */
    #[Computed]
    public function requirements(): Collection
    {
        Gate::authorize('crew.settings.manage');

        return $this->kind === 'roles' && $this->editingId !== null
            ? CrewRole::findOrFail($this->editingId)->requirements()->with(['skill', 'qualification'])->get()
            : collect();
    }

    public function addRequirement(): void
    {
        Gate::authorize('crew.settings.manage');
        abort_unless($this->kind === 'roles' && $this->editingId !== null, 404);
        $this->validate([
            'requirement_type' => ['required', 'in:skill,qualification'],
            'requirement_skill_id' => ['nullable', 'integer', 'exists:crew_skills,id'],
            'requirement_qualification_id' => ['nullable', 'integer', 'exists:crew_qualifications,id'],
            'is_mandatory' => ['boolean'],
        ]);
        $this->performCrewAction(function (): void {
            $rows = $this->requirements->map(function ($requirement): array {
                return [
                    'requirement_type' => $requirement->requirement_type->value,
                    'crew_skill_id' => $requirement->crew_skill_id,
                    'crew_qualification_id' => $requirement->crew_qualification_id,
                    'is_mandatory' => $requirement->is_mandatory,
                    'min_proficiency' => $requirement->min_proficiency?->value,
                    'min_years_experience' => $requirement->min_years_experience,
                ];
            })->all();
            $rows[] = [
                'requirement_type' => $this->requirement_type,
                'crew_skill_id' => $this->requirement_type === 'skill' ? $this->requirement_skill_id : null,
                'crew_qualification_id' => $this->requirement_type === 'qualification' ? $this->requirement_qualification_id : null,
                'is_mandatory' => $this->is_mandatory,
            ];
            app(SyncCrewRoleRequirements::class)(CrewRole::findOrFail($this->editingId), new SyncRoleRequirementsData($rows));
            unset($this->requirements);
        });
    }

    public function removeRequirement(int $id): void
    {
        Gate::authorize('crew.settings.manage');
        abort_unless($this->kind === 'roles' && $this->editingId !== null, 404);
        $this->performCrewAction(function () use ($id): void {
            $rows = $this->requirements->reject(function ($requirement) use ($id): bool {
                return $requirement->id === $id;
            })->map(function ($requirement): array {
                return [
                    'requirement_type' => $requirement->requirement_type->value,
                    'crew_skill_id' => $requirement->crew_skill_id,
                    'crew_qualification_id' => $requirement->crew_qualification_id,
                    'is_mandatory' => $requirement->is_mandatory,
                    'min_proficiency' => $requirement->min_proficiency?->value,
                    'min_years_experience' => $requirement->min_years_experience,
                ];
            })->values()->all();
            app(SyncCrewRoleRequirements::class)(CrewRole::findOrFail($this->editingId), new SyncRoleRequirementsData($rows));
            unset($this->requirements);
        });
    }

    public function mount(string $kind = 'roles'): void
    {
        Gate::authorize('crew.settings.manage');
        abort_unless(in_array($kind, ['roles', 'skills', 'qualifications'], true), 404);
        $this->kind = $kind;
    }

    protected function modelClass(): string
    {
        return match ($this->kind) {
            'roles' => CrewRole::class,'skills' => CrewSkill::class,'qualifications' => CrewQualification::class,
            default => abort(404),
        };
    }

    /** @return LengthAwarePaginator<int, CrewRole|CrewSkill|CrewQualification> */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        Gate::authorize('crew.settings.manage');

        return ($this->modelClass())::orderBy('name')->paginate(25);
    }

    public function edit(int $id): void
    {
        Gate::authorize('crew.settings.manage');
        $record = ($this->modelClass())::findOrFail($id);
        $this->editingId = $id;
        $this->fill($record->only(['name', 'description', 'is_active']));
        if ($this->kind === 'skills') {
            $this->category = $record->category;
        }
        if ($this->kind === 'qualifications') {
            $this->issuer = $record->issuer;
            $this->requires_expiry = $record->requires_expiry;
        }
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'name', 'description', 'category', 'issuer', 'requires_expiry', 'is_active');
        $this->resetValidation();
    }

    public function save(): void
    {
        Gate::authorize('crew.settings.manage');
        $data = $this->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'is_active' => ['boolean']]);
        $singular = match ($this->kind) {
            'roles' => 'Role','skills' => 'Skill','qualifications' => 'Qualification',
            default => abort(404),
        };
        if ($this->kind === 'skills') {
            $this->validate(['category' => ['required', 'string', 'max:100']]);
            $data['category'] = $this->category;
        }
        if ($this->kind === 'qualifications') {
            $this->validate(['issuer' => ['nullable', 'string', 'max:255'], 'requires_expiry' => ['boolean']]);
            $data['issuer'] = $this->issuer;
            $data['requires_expiry'] = $this->requires_expiry;
        }
        $this->performCrewAction(function () use ($data, $singular): void {
            if ($this->editingId !== null) {
                $record = ($this->modelClass())::findOrFail($this->editingId);
                $data += $record->only(['slug', 'is_supervisory', 'requires_certification', 'sort_order', 'validity_months', 'is_mandatory']);
                $dataClass = 'App\\Domain\\Crew\\Data\\Registry\\UpdateCrew'.$singular.'Data';
                $actionClass = 'App\\Domain\\Crew\\Actions\\Registry\\UpdateCrew'.$singular;
                app($actionClass)($record, $dataClass::from($data));
            } else {
                $dataClass = 'App\\Domain\\Crew\\Data\\Registry\\CreateCrew'.$singular.'Data';
                $actionClass = 'App\\Domain\\Crew\\Actions\\Registry\\CreateCrew'.$singular;
                app($actionClass)($dataClass::from($data));
            }
            $this->cancelEdit();
            unset($this->records);
        });
    }

    public function render(): View
    {
        return view('crew::configuration');
    }
}
