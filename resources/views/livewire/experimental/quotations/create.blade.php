<?php

use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Admin\InviteUser;
use App\Actions\Members\CreateMember;
use App\Actions\Stores\CreateStore;
use App\Data\Admin\InviteUserData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Members\CreateMemberData;
use App\Data\Stores\CreateStoreData;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Create Experimental Quotation')] class extends Component
{
    public string $subject = '';

    public ?int $memberId = null;

    public ?int $storeId = null;

    public ?int $venueId = null;

    public ?int $ownedBy = null;

    public ?string $reference = null;

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public string $memberSearch = '';

    public string $storeSearch = '';

    public string $venueSearch = '';

    public string $ownerSearch = '';

    public string $newClientName = '';

    public string $newVenueName = '';

    public string $newStoreName = '';

    public string $newOwnerName = '';

    public string $newOwnerEmail = '';

    public function mount(): void
    {
        Gate::authorize('opportunities.create');

        $this->ownedBy = auth()->user()?->member_id;
        $this->storeId = Store::query()->where('is_default', true)->value('id')
            ?? Store::query()->orderBy('id')->value('id');
    }

    /** @return Collection<int, Member> */
    #[Computed]
    public function members(): Collection
    {
        $members = Member::query()
            ->where('membership_type', MembershipType::Organisation)
            ->where('is_active', true)
            ->when(filled($this->memberSearch), fn ($query) => $query->where('name', 'like', '%'.trim($this->memberSearch).'%'))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        if ($this->memberId !== null && ! $members->contains('id', $this->memberId)) {
            $selected = Member::query()
                ->whereKey($this->memberId)
                ->where('membership_type', MembershipType::Organisation)
                ->where('is_active', true)
                ->first(['id', 'name']);

            if ($selected !== null) {
                $members->prepend($selected);
            }
        }

        return $members;
    }

    /** @return Collection<int, Member> */
    #[Computed]
    public function venues(): Collection
    {
        $venues = Member::query()
            ->where('membership_type', MembershipType::Venue)
            ->where('is_active', true)
            ->when(filled($this->venueSearch), fn ($query) => $query->where('name', 'like', '%'.trim($this->venueSearch).'%'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        if ($this->venueId !== null && ! $venues->contains('id', $this->venueId)) {
            $selected = Member::query()
                ->whereKey($this->venueId)
                ->where('membership_type', MembershipType::Venue)
                ->where('is_active', true)
                ->first(['id', 'name']);

            if ($selected !== null) {
                $venues->prepend($selected);
            }
        }

        return $venues;
    }

    /** @return Collection<int, Store> */
    #[Computed]
    public function stores(): Collection
    {
        $stores = Store::query()
            ->when(filled($this->storeSearch), fn ($query) => $query->where('name', 'like', '%'.trim($this->storeSearch).'%'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        if ($this->storeId !== null && ! $stores->contains('id', $this->storeId)) {
            $selected = Store::query()->whereKey($this->storeId)->first(['id', 'name']);

            if ($selected !== null) {
                $stores->prepend($selected);
            }
        }

        return $stores;
    }

    /** @return Collection<int, Member> */
    #[Computed]
    public function owners(): Collection
    {
        $owners = Member::query()
            ->where('membership_type', MembershipType::User)
            ->where('is_active', true)
            ->when(filled($this->ownerSearch), fn ($query) => $query->where('name', 'like', '%'.trim($this->ownerSearch).'%'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        if ($this->ownedBy !== null && ! $owners->contains('id', $this->ownedBy)) {
            $selected = Member::query()
                ->whereKey($this->ownedBy)
                ->where('membership_type', MembershipType::User)
                ->where('is_active', true)
                ->first(['id', 'name']);

            if ($selected !== null) {
                $owners->prepend($selected);
            }
        }

        return $owners;
    }

    public function createClient(): void
    {
        $this->validate(['newClientName' => ['required', 'string', 'max:255']]);

        $client = (new CreateMember)(CreateMemberData::from([
            'name' => trim($this->newClientName),
            'membership_type' => MembershipType::Organisation,
        ]));

        $this->memberId = $client->id;
        $this->memberSearch = '';
        $this->newClientName = '';
        $this->modal('create-client')->close();
    }

    public function createVenue(): void
    {
        $this->validate(['newVenueName' => ['required', 'string', 'max:255']]);

        $venue = (new CreateMember)(CreateMemberData::from([
            'name' => trim($this->newVenueName),
            'membership_type' => MembershipType::Venue,
        ]));

        $this->venueId = $venue->id;
        $this->venueSearch = '';
        $this->newVenueName = '';
        $this->modal('create-venue')->close();
    }

    public function createStore(): void
    {
        $this->validate(['newStoreName' => ['required', 'string', 'max:255']]);

        $store = (new CreateStore)(CreateStoreData::from([
            'name' => trim($this->newStoreName),
        ]));

        $this->storeId = $store->id;
        $this->storeSearch = '';
        $this->newStoreName = '';
        $this->modal('create-store')->close();
    }

    public function createOwner(): void
    {
        $this->validate([
            'newOwnerName' => ['required', 'string', 'max:255'],
            'newOwnerEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $owner = (new InviteUser)(InviteUserData::from([
            'name' => trim($this->newOwnerName),
            'email' => trim($this->newOwnerEmail),
        ]));

        $this->ownedBy = $owner->member_id;
        $this->ownerSearch = '';
        $this->newOwnerName = '';
        $this->newOwnerEmail = '';
        $this->modal('create-owner')->close();
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
            $result = (new CreateOpportunity)(CreateOpportunityData::from([
                'subject' => $this->subject,
                'member_id' => $this->memberId,
                'store_id' => $this->storeId,
                'venue_id' => $this->venueId,
                'owned_by' => $this->ownedBy,
                'reference' => $this->reference ?: null,
                'starts_at' => $this->startsAt,
                'ends_at' => $this->endsAt,
                'currency' => settings('company.base_currency', 'GBP'),
            ]));

            $opportunity = \App\Models\Opportunity::query()->findOrFail($result->id);
            (new ConvertToQuotation)($opportunity);

            $this->redirect(route('experimental.quotations.edit', $opportunity->refresh()), navigate: true);
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
    <x-dense::page-header title="Create Quotation">
        <x-slot:breadcrumbs>
            <a href="{{ route('experimental.quotations.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Experimental Quotations</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>Create</span>
        </x-slot:breadcrumbs>
        <x-slot:meta><span class="s-badge s-badge-amber">Experimental</span></x-slot:meta>
    </x-dense::page-header>

    <div class="w-full px-6 py-4 max-md:px-5 max-sm:px-3">

        <form wire:submit="save" class="space-y-5">
            <x-signals.form-section title="Quotation details">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="subject" size="sm" label="Subject" required />
                    <flux:input wire:model="reference" size="sm" label="Reference" />

                    <div class="flex w-full items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <flux:select wire:model="memberId" class="w-full" size="sm" variant="combobox" :filter="false" label="Client" clearable>
                                <x-slot name="input">
                                    <flux:select.input wire:model.live.debounce.300ms="memberSearch" size="sm" icon="magnifying-glass" placeholder="Search client..." />
                                </x-slot>
                                @foreach($this->members as $member)
                                    <flux:select.option wire:key="member-{{ $member->id }}" value="{{ $member->id }}">
                                        {{ $member->name }}
                                    </flux:select.option>
                                @endforeach
                                <flux:select.option.create modal="create-client" min-length="2">
                                    Create client "<span wire:text="memberSearch"></span>"
                                </flux:select.option.create>
                            </flux:select>
                        </div>
                        <flux:modal.trigger name="create-client">
                            <flux:button type="button" icon="plus" size="sm" aria-label="Add client" title="Add client" />
                        </flux:modal.trigger>
                    </div>

                    <div class="flex w-full items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <flux:select wire:model="storeId" class="w-full" size="sm" variant="combobox" :filter="false" label="Store" clearable>
                                <x-slot name="input">
                                    <flux:select.input wire:model.live.debounce.300ms="storeSearch" size="sm" icon="magnifying-glass" placeholder="Search store..." />
                                </x-slot>
                                @foreach($this->stores as $store)
                                    <flux:select.option wire:key="store-{{ $store->id }}" value="{{ $store->id }}">
                                        {{ $store->name }}
                                    </flux:select.option>
                                @endforeach
                                <flux:select.option.create modal="create-store" min-length="2">
                                    Create store "<span wire:text="storeSearch"></span>"
                                </flux:select.option.create>
                            </flux:select>
                        </div>
                        <flux:modal.trigger name="create-store">
                            <flux:button class="shrink-0" type="button" icon="plus" size="sm" aria-label="Add store" title="Add store" />
                        </flux:modal.trigger>
                    </div>

                    <div class="flex w-full items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <flux:select wire:model="venueId" class="w-full" size="sm" variant="combobox" :filter="false" label="Venue" clearable>
                                <x-slot name="input">
                                    <flux:select.input wire:model.live.debounce.300ms="venueSearch" size="sm" icon="magnifying-glass" placeholder="Search venue..." />
                                </x-slot>
                                @foreach($this->venues as $venue)
                                    <flux:select.option wire:key="venue-{{ $venue->id }}" value="{{ $venue->id }}">
                                        {{ $venue->name }}
                                    </flux:select.option>
                                @endforeach
                                <flux:select.option.create modal="create-venue" min-length="2">
                                    Create venue "<span wire:text="venueSearch"></span>"
                                </flux:select.option.create>
                            </flux:select>
                        </div>
                        <flux:modal.trigger name="create-venue">
                            <flux:button class="shrink-0" type="button" icon="plus" size="sm" aria-label="Add venue" title="Add venue" />
                        </flux:modal.trigger>
                    </div>

                    <div class="flex w-full items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <flux:select wire:model="ownedBy" class="w-full" size="sm" variant="combobox" :filter="false" label="Owner" clearable>
                                <x-slot name="input">
                                    <flux:select.input wire:model.live.debounce.300ms="ownerSearch" size="sm" icon="magnifying-glass" placeholder="Search owner..." />
                                </x-slot>
                                @foreach($this->owners as $owner)
                                    <flux:select.option wire:key="owner-{{ $owner->id }}" value="{{ $owner->id }}">
                                        {{ $owner->name }}
                                    </flux:select.option>
                                @endforeach
                                <flux:select.option.create modal="create-owner" min-length="2">
                                    Invite owner "<span wire:text="ownerSearch"></span>"
                                </flux:select.option.create>
                            </flux:select>
                        </div>
                        <flux:modal.trigger name="create-owner">
                            <flux:button class="shrink-0" type="button" icon="plus" size="sm" aria-label="Add owner" title="Add owner" />
                        </flux:modal.trigger>
                    </div>
                </div>
            </x-signals.form-section>
            <x-signals.form-section title="Schedule">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:date-picker wire:model="startsAt" size="sm" label="Starts" placeholder="Select start date" clearable />
                    <flux:date-picker wire:model="endsAt" size="sm" label="Ends" placeholder="Select end date" clearable />
                </div>
            </x-signals.form-section>

            <div class="flex justify-end gap-2">
                <a href="{{ route('experimental.quotations.index') }}" wire:navigate class="s-btn s-btn-ghost">Cancel</a>
                <button type="submit" class="s-btn s-btn-primary">Create quotation</button>
            </div>
        </form>

        <flux:modal name="create-client" flyout position="right" class="w-full max-w-md">
            <form wire:submit="createClient" class="space-y-6">
                <div>
                    <flux:heading size="lg">Create client</flux:heading>
                    <flux:text class="mt-2">Add a new organisation for this quotation.</flux:text>
                </div>
                <flux:input wire:model="newClientName" size="sm" label="Name" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button type="button" variant="ghost" size="sm">Cancel</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" size="sm">Create client</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="create-store" flyout position="right" class="w-full max-w-md">
            <form wire:submit="createStore" class="space-y-6">
                <div>
                    <flux:heading size="lg">Create store</flux:heading>
                    <flux:text class="mt-2">Add a new store location.</flux:text>
                </div>
                <flux:input wire:model="newStoreName" size="sm" label="Name" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button type="button" variant="ghost" size="sm">Cancel</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" size="sm">Create store</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="create-venue" flyout position="right" class="w-full max-w-md">
            <form wire:submit="createVenue" class="space-y-6">
                <div>
                    <flux:heading size="lg">Create venue</flux:heading>
                    <flux:text class="mt-2">Add a new venue member.</flux:text>
                </div>
                <flux:input wire:model="newVenueName" size="sm" label="Name" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button type="button" variant="ghost" size="sm">Cancel</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" size="sm">Create venue</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="create-owner" flyout position="right" class="w-full max-w-md">
            <form wire:submit="createOwner" class="space-y-6">
                <div>
                    <flux:heading size="lg">Invite owner</flux:heading>
                    <flux:text class="mt-2">Owners are created through the canonical user invitation flow.</flux:text>
                </div>
                <flux:input wire:model="newOwnerName" size="sm" label="Name" required />
                <flux:input wire:model="newOwnerEmail" size="sm" type="email" label="Email" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button type="button" variant="ghost" size="sm">Cancel</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" size="sm">Invite owner</flux:button>
                </div>
            </form>
        </flux:modal>

    </div>
</section>
