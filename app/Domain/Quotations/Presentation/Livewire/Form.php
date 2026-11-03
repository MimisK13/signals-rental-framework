<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Presentation\Livewire;

use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Member;
use App\Domain\Projects\Models\Project;
use App\Domain\Quotations\Actions\CreateQuotation;
use App\Domain\Quotations\Data\CreateQuotationData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Stores\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('New Quotation')]
final class Form extends Component
{
    public ?int $projectId = null;

    public string $subject = '';

    public ?int $memberId = null;

    public ?int $venueId = null;

    public ?int $storeId = null;

    public ?int $ownerId = null;

    public ?string $reference = null;

    public ?string $description = null;

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public ?string $validUntil = null;

    public ?string $currencyCode = null;

    public bool $pricesIncludeTax = false;

    public function mount(?Project $project = null): void
    {
        Gate::authorize('create', Quotation::class);

        if ($project !== null) {
            $this->projectId = $project->id;
            $this->subject = $project->name;
            $this->memberId = $project->member_id;
            $this->venueId = $project->venue_id;
            $this->ownerId = $project->owned_by;
            $this->startsAt = $project->starts_at?->format('Y-m-d\TH:i');
            $this->endsAt = $project->ends_at?->format('Y-m-d\TH:i');
        }

        $this->storeId = Store::query()->where('is_default', true)->value('id')
            ?? Store::query()->orderBy('id')->value('id');
        $this->currencyCode = (string) settings('company.base_currency', 'GBP');
        $this->pricesIncludeTax = (bool) settings('finance.prices_include_tax_default', false);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'projectId' => ['nullable', 'integer', 'exists:projects,id'],
            'memberId' => ['required', 'integer', 'exists:members,id'],
            'venueId' => ['nullable', 'integer', 'exists:members,id'],
            'storeId' => ['required', 'integer', 'exists:stores,id'],
            'ownerId' => ['nullable', 'integer', 'exists:members,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'validUntil' => ['nullable', 'date'],
            'currencyCode' => ['required', 'string', 'size:3'],
            'pricesIncludeTax' => ['boolean'],
        ]);

        $created = app(CreateQuotation::class)(CreateQuotationData::from([
            'subject' => $validated['subject'],
            'store_id' => $validated['storeId'],
            'project_id' => $validated['projectId'],
            'member_id' => $validated['memberId'],
            'venue_id' => $validated['venueId'],
            'owned_by' => $validated['ownerId'],
            'reference' => $validated['reference'],
            'description' => $validated['description'],
            'starts_at' => $validated['startsAt'],
            'ends_at' => $validated['endsAt'],
            'valid_until' => $validated['validUntil'],
            'currency_code' => $validated['currencyCode'],
            'prices_include_tax' => $validated['pricesIncludeTax'],
        ]));

        $this->redirectRoute('quotations.edit', ['quotation' => $created->id], navigate: true);
    }

    public function updatedProjectId(?int $projectId): void
    {
        if ($projectId === null) {
            return;
        }

        $project = Project::query()->findOrFail($projectId);
        $this->subject = $this->subject !== '' ? $this->subject : $project->name;
        $this->memberId = $project->member_id;
        $this->venueId = $project->venue_id;
        $this->ownerId = $project->owned_by;
        $this->startsAt ??= $project->starts_at?->format('Y-m-d\TH:i');
        $this->endsAt ??= $project->ends_at?->format('Y-m-d\TH:i');
    }

    /** @return Collection<int, Member> */
    private function members(MembershipType $type): Collection
    {
        return Member::query()->ofType($type)->orderBy('name')->get(['id', 'name']);
    }

    public function render(): View
    {
        return view('quotations::form', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'customers' => $this->members(MembershipType::Organisation),
            'venues' => $this->members(MembershipType::Venue),
            'owners' => $this->members(MembershipType::User),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
