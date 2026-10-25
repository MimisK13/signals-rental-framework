<?php

use App\Actions\Opportunities\UpdateOpportunity;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Enums\MembershipType;
use App\Enums\OpportunityState;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Edit Experimental Quotation')] class extends Component
{
    public Opportunity $opportunity;

    public string $subject = '';

    public ?int $memberId = null;

    public ?int $storeId = null;

    public ?int $venueId = null;

    public ?int $ownedBy = null;

    public ?string $reference = null;

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public int $chargeTotalMinor = 0;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.edit');
        abort_unless($opportunity->state === OpportunityState::Quotation, 404);

        $this->opportunity = $opportunity;
        $this->loadFields();
    }

    private function loadFields(): void
    {
        $this->subject = $this->opportunity->subject;
        $this->memberId = $this->opportunity->member_id;
        $this->storeId = $this->opportunity->store_id;
        $this->venueId = $this->opportunity->venue_id;
        $this->ownedBy = $this->opportunity->owned_by;
        $this->reference = $this->opportunity->reference;
        $this->startsAt = $this->opportunity->starts_at?->format('Y-m-d\\TH:i');
        $this->endsAt = $this->opportunity->ends_at?->format('Y-m-d\\TH:i');
        $this->chargeTotalMinor = (int) ($this->opportunity->charge_total ?? 0);
    }

    /** @return Collection<int, Member> */
    #[Computed]
    public function members(): Collection
    {
        return Member::query()->where('membership_type', MembershipType::Organisation)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, Member> */
    #[Computed]
    public function venues(): Collection
    {
        return Member::query()->where('membership_type', MembershipType::Venue)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, Store> */
    #[Computed]
    public function stores(): Collection
    {
        return Store::query()->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, Member> */
    #[Computed]
    public function owners(): Collection
    {
        return Member::query()->where('membership_type', MembershipType::User)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[On('opportunity-totals-updated')]
    public function onTotalsUpdated(?int $chargeTotalMinor = null): void
    {
        if ($chargeTotalMinor !== null) {
            $this->chargeTotalMinor = $chargeTotalMinor;
        }
    }

    public function save(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'memberId' => ['nullable', 'integer', Rule::exists('members', 'id')->where('membership_type', MembershipType::Organisation->value)->withoutTrashed()],
            'storeId' => ['nullable', 'integer', 'exists:stores,id'],
            'venueId' => ['nullable', 'integer', Rule::exists('members', 'id')->where('membership_type', MembershipType::Venue->value)->withoutTrashed()],
            'ownedBy' => ['nullable', 'integer', Rule::exists('members', 'id')->withoutTrashed()],
            'reference' => ['nullable', 'string', 'max:255'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
        ]);

        try {
            (new UpdateOpportunity)($this->opportunity->fresh(), UpdateOpportunityData::from([
                'subject' => $this->subject,
                'member_id' => $this->memberId,
                'store_id' => $this->storeId,
                'venue_id' => $this->venueId,
                'owned_by' => $this->ownedBy,
                'reference' => $this->reference,
                'starts_at' => $this->startsAt,
                'ends_at' => $this->endsAt,
            ]));

            $this->opportunity->refresh();
            $this->loadFields();
            $this->dispatch('toast', type: 'success', message: 'Quotation updated.');
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError(match ($field) {
                    'member_id' => 'memberId',
                    'store_id' => 'storeId',
                    'venue_id' => 'venueId',
                    'owned_by' => 'ownedBy',
                    'starts_at' => 'startsAt',
                    'ends_at' => 'endsAt',
                    default => $field,
                }, $messages);
            }
        }
    }
}; ?>

<section class="w-full">
    <x-dense::page-header title="Edit Quotation">
        <x-slot:breadcrumbs>
            <a href="{{ route('experimental.quotations.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Experimental Quotations</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $opportunity->number }}</span>
        </x-slot:breadcrumbs>
        <x-slot:meta><span class="s-badge s-badge-amber">Experimental</span></x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('opportunities.show', $opportunity) }}" wire:navigate.hover class="s-btn s-btn-ghost">Open canonical view</a>
        </x-slot:actions>
    </x-dense::page-header>

    <div class="px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-dense::card title="Quotation details" collapsible :open="false" class="mb-4">
            <form wire:submit="save" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-3">
                    <flux:input wire:model="subject" label="Subject" required />
                    <flux:input wire:model="reference" label="Reference" />
                    <div class="flex flex-col justify-end">
                        <span class="s-field-label">Charge total</span>
                        <strong class="font-mono text-lg">{{ $opportunity->formatMoneyCost('charge_total') }}</strong>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <flux:select wire:model="memberId" label="Client">
                        <flux:select.option value="">Keep current client</flux:select.option>
                        @foreach($this->members as $member)<flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>@endforeach
                    </flux:select>
                    <flux:select wire:model="storeId" label="Store">
                        <flux:select.option value="">Keep current store</flux:select.option>
                        @foreach($this->stores as $store)<flux:select.option value="{{ $store->id }}">{{ $store->name }}</flux:select.option>@endforeach
                    </flux:select>
                    <flux:select wire:model="venueId" label="Venue">
                        <flux:select.option value="">No venue</flux:select.option>
                        @foreach($this->venues as $venue)<flux:select.option value="{{ $venue->id }}">{{ $venue->name }}</flux:select.option>@endforeach
                    </flux:select>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <flux:select wire:model="ownedBy" label="Owner">
                        <flux:select.option value="">No owner</flux:select.option>
                        @foreach($this->owners as $owner)<flux:select.option value="{{ $owner->id }}">{{ $owner->name }}</flux:select.option>@endforeach
                    </flux:select>
                    <flux:input wire:model="startsAt" type="datetime-local" label="Starts" />
                    <flux:input wire:model="endsAt" type="datetime-local" label="Ends" />
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="s-btn s-btn-primary">Save quotation</button>
                </div>
            </form>
        </x-dense::card>

        <livewire:experimental.quotation-editor :opportunity="$opportunity" :key="'experimental-quotation-editor-'.$opportunity->id" />
    </div>
</section>
