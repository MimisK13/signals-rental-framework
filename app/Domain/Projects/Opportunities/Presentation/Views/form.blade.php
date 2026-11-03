
<section class="w-full">
    @if($isEditing && $opportunity)
        @include('project-opportunities::partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Edit'])
        @include('project-opportunities::partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => ''])
    @else
        <x-dense::page-header title="New Opportunity">
            <x-slot:breadcrumbs>
                <a href="{{ route('opportunities.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Opportunities</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-dense::page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">

                {{-- ======================================== --}}
                {{-- LEFT COLUMN — Core header fields --}}
                {{-- ======================================== --}}
                <div class="space-y-6">
                    <x-signals.form-section title="Basic Info">
                        <div class="space-y-3">
                            <flux:input wire:model="subject" label="Subject" required />
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="storeId" label="Store">
                                    <option value="">None</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="reference" label="Reference" />
                            </div>
                            <flux:textarea wire:model="description" label="Description" rows="2" />
                            <flux:textarea wire:model="externalDescription" label="External Description" rows="2"
                                description="Shown on customer-facing documents." />
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Hire Period">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <div>
                                    <label class="s-field-label mb-1 block">Starts At</label>
                                    <x-signals.datetime-input
                                        :value="$startsAt"
                                        placeholder="Select date & time"
                                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('startsAt', $event.detail)"
                                    />
                                </div>
                                <div>
                                    <label class="s-field-label mb-1 block">Ends At</label>
                                    {{-- When "Ends At" is empty, open its calendar on the chosen
                                         "Starts At" month rather than today (anchor). The wire:key
                                         re-inits the picker when Starts At changes so a freshly
                                         picked start month takes effect immediately. --}}
                                    <x-signals.datetime-input
                                        wire:key="opp-ends-at-{{ $startsAt ? \Carbon\Carbon::parse($startsAt)->format('Ymd') : 'none' }}"
                                        :value="$endsAt"
                                        :anchor="$startsAt"
                                        placeholder="Select date & time"
                                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('endsAt', $event.detail)"
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <div>
                                    <label class="s-field-label mb-1 block">Charge Starts At</label>
                                    <x-signals.datetime-input
                                        :value="$chargeStartsAt"
                                        placeholder="Select date & time"
                                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('chargeStartsAt', $event.detail)"
                                    />
                                </div>
                                <div>
                                    <label class="s-field-label mb-1 block">Charge Ends At</label>
                                    <x-signals.datetime-input
                                        :value="$chargeEndsAt"
                                        placeholder="Select date & time"
                                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('chargeEndsAt', $event.detail)"
                                    />
                                </div>
                            </div>
                            <div class="rounded-lg border border-zinc-200/70 p-3 dark:border-zinc-700/70">
                                <div class="mb-3 text-sm font-medium">Planning Period</div>
                                <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">
                                    Capacity is committed for this period. It may be wider than the customer usage period above.
                                </p>
                                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                    <div>
                                        <label class="s-field-label mb-1 block">Planning Starts At</label>
                                        <x-signals.datetime-input
                                            :value="$planningStartsAt"
                                            placeholder="Select date & time"
                                            x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('planningStartsAt', $event.detail)"
                                        />
                                    </div>
                                    <div>
                                        <label class="s-field-label mb-1 block">Planning Ends At</label>
                                        <x-signals.datetime-input
                                            :value="$planningEndsAt"
                                            :anchor="$planningStartsAt"
                                            placeholder="Select date & time"
                                            x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('planningEndsAt', $event.detail)"
                                        />
                                    </div>
                                </div>
                            </div>
                            <flux:input wire:model="reservationPriority" type="number" min="0"
                                label="Reservation Priority"
                                description="Higher values win deterministic capacity conflicts." />
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Schedule & Logistics">
                        <div class="space-y-4">
                            {{-- Event-logistics lifecycle date pairs (prep → load → deliver → setup → show → takedown → collect → unload → deprep). --}}
                            @php
                                $lifecyclePhases = [
                                    ['Prep', 'prepStartsAt', 'prepEndsAt'],
                                    ['Load', 'loadStartsAt', 'loadEndsAt'],
                                    ['Deliver', 'deliverStartsAt', 'deliverEndsAt'],
                                    ['Setup', 'setupStartsAt', 'setupEndsAt'],
                                    ['Show', 'showStartsAt', 'showEndsAt'],
                                    ['Takedown', 'takedownStartsAt', 'takedownEndsAt'],
                                    ['Collect', 'collectStartsAt', 'collectEndsAt'],
                                    ['Unload', 'unloadStartsAt', 'unloadEndsAt'],
                                    ['Deprep', 'deprepStartsAt', 'deprepEndsAt'],
                                ];
                            @endphp
                            @foreach($lifecyclePhases as [$phaseLabel, $startProp, $endProp])
                                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1" wire:key="phase-{{ $startProp }}">
                                    <div>
                                        <label class="s-field-label mb-1 block">{{ $phaseLabel }} Starts</label>
                                        <x-signals.datetime-input
                                            :value="$$startProp"
                                            placeholder="Select date & time"
                                            x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('{{ $startProp }}', $event.detail)"
                                        />
                                    </div>
                                    <div>
                                        <label class="s-field-label mb-1 block">{{ $phaseLabel }} Ends</label>
                                        <x-signals.datetime-input
                                            :value="$$endProp"
                                            placeholder="Select date & time"
                                            x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('{{ $endProp }}', $event.detail)"
                                        />
                                    </div>
                                </div>
                            @endforeach

                            {{-- Milestone datetimes. --}}
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <div>
                                    <label class="s-field-label mb-1 block">Ordered At</label>
                                    <x-signals.datetime-input
                                        :value="$orderedAt"
                                        placeholder="Select date & time"
                                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('orderedAt', $event.detail)"
                                    />
                                </div>
                                <div>
                                    <label class="s-field-label mb-1 block">Quote Expires At</label>
                                    <x-signals.datetime-input
                                        :value="$quoteInvalidAt"
                                        placeholder="Select date & time"
                                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('quoteInvalidAt', $event.detail)"
                                    />
                                </div>
                            </div>

                            {{-- Customer collect/return self-service flags. --}}
                            <div class="space-y-2 pt-1">
                                <flux:checkbox wire:model="customerCollecting" label="Customer collecting"
                                    description="The customer collects the goods themselves." />
                                <flux:checkbox wire:model="customerReturning" label="Customer returning"
                                    description="The customer returns the goods themselves." />
                            </div>

                            {{-- Delivery / collection instructions. --}}
                            <flux:textarea wire:model="deliveryInstructions" label="Delivery Instructions" rows="2" />
                            <flux:textarea wire:model="collectionInstructions" label="Collection Instructions" rows="2" />
                        </div>
                    </x-signals.form-section>

                    {{-- Delivery & collection addresses (C-data-2). Each picker is --}}
                    {{-- populated from the selected member's addresses; "Add new --}}
                    {{-- address" opens a modal that creates one on the member. --}}
                    <x-signals.form-section title="Delivery & Collection">
                        <div class="space-y-4">
                            @if($memberId === null)
                                <p class="s-field-hint text-[var(--text-muted)]">Select a member to choose delivery and collection addresses.</p>
                            @endif

                            @php
                                $addressFields = [
                                    ['Delivery Address', 'deliveryAddressId', 'delivery'],
                                    ['Collection Address', 'collectionAddressId', 'collection'],
                                ];
                            @endphp
                            @foreach($addressFields as [$addressLabel, $addressProp, $addressTarget])
                                <div wire:key="opp-address-{{ $addressTarget }}">
                                    <label class="s-field-label mb-1 block">{{ $addressLabel }}</label>
                                    <div class="flex items-end gap-2">
                                        <div class="flex-1">
                                            <flux:select wire:model="{{ $addressProp }}" :disabled="$memberId === null">
                                                <option value="">None</option>
                                                @foreach($memberAddresses as $address)
                                                    <option value="{{ $address->id }}" wire:key="{{ $addressTarget }}-opt-{{ $address->id }}">
                                                        {{ \Illuminate\Support\Str::limit(collect([$address->name, $address->street, $address->city, $address->postcode])->filter()->implode(', '), 70) ?: 'Address #'.$address->id }}
                                                    </option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                        <flux:button type="button" variant="filled" wire:click="openAddressModal('{{ $addressTarget }}')" :disabled="$memberId === null">
                                            Add new address
                                        </flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Pricing">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                @if($isEditing)
                                    <div>
                                        <label class="s-field-label mb-1 block">Currency</label>
                                        <div class="flex h-9 items-center">
                                            <span class="s-badge s-badge-zinc">{{ $currency }}</span>
                                        </div>
                                    </div>
                                @else
                                    <flux:select wire:model="currency" label="Currency">
                                        @foreach($currencies as $currencyOption)
                                            <option value="{{ $currencyOption->code }}">{{ $currencyOption->code }} — {{ $currencyOption->name }}</option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            </div>

                            @if($isEditing)
                                <div class="flex items-center gap-2 pt-1">
                                    <span class="s-field-label">Prices include tax:</span>
                                    <span class="s-badge {{ $pricesIncludeTax ? 's-badge-green' : 's-badge-zinc' }}">{{ $pricesIncludeTax ? 'Yes' : 'No' }}</span>
                                </div>
                            @else
                                <flux:checkbox wire:model="pricesIncludeTax" label="Prices include tax"
                                    description="Whether entered prices are tax-inclusive. Set at creation only." />
                            @endif

                            {{-- Chargeable-days + open-ended-rental controls (C3b). --}}
                            <div class="space-y-3 pt-2">
                                <flux:checkbox wire:model.live="useChargeableDays" label="Use chargeable days"
                                    description="Bill on a manual chargeable-day count rather than the hire period." />
                                <div x-show="$wire.useChargeableDays" x-cloak>
                                    <flux:input wire:model="chargeableDays" type="number" step="0.1" min="0"
                                        label="Chargeable Days" placeholder="e.g. 2.5" />
                                </div>
                                <flux:checkbox wire:model="openEndedRental" label="Open-ended rental"
                                    description="The rental has no fixed return date." />
                            </div>
                        </div>
                    </x-signals.form-section>

                    {{-- Actions --}}
                    <div class="flex items-center gap-4 pt-2">
                        <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Opportunity' }}</flux:button>
                        <flux:button variant="ghost" href="{{ $isEditing ? route('opportunities.show', $opportunityId) : route('opportunities.index') }}" wire:navigate.hover>Cancel</flux:button>
                    </div>
                </div>

                {{-- ======================================== --}}
                {{-- RIGHT COLUMN — Parties, tags, custom fields --}}
                {{-- ======================================== --}}
                <div class="space-y-6" style="position: sticky; top: 24px;">
                    <x-signals.form-section title="Parties">
                        <div class="space-y-3">
                            {{-- Member picker — Organisation customers only --}}
                            <div>
                                <label class="block text-sm font-medium mb-1">Organisation</label>
                                @if($memberSelectedName)
                                    <div class="flex items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-secondary)] px-3 py-2">
                                        <span class="flex-1 truncate">{{ $memberSelectedName }}</span>
                                        <button type="button" wire:click="clearMember" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] shrink-0">&times;</button>
                                    </div>
                                @else
                                    <div x-data="{ open: false }" x-on:click.away="open = false" class="relative">
                                        <flux:input
                                            wire:model.live.debounce.300ms="memberSearch"
                                            placeholder="Search organisations..."
                                            x-on:focus="open = true"
                                            x-on:input="open = true"
                                            autocomplete="off"
                                        />
                                        @if($this->memberOptions->isNotEmpty())
                                            <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg-primary)] shadow-lg max-h-60 overflow-y-auto">
                                                @foreach($this->memberOptions as $option)
                                                    <button
                                                        type="button"
                                                        wire:key="member-{{ $option->id }}"
                                                        wire:click="selectMember({{ $option->id }})"
                                                        x-on:click="open = false"
                                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--bg-secondary)] transition-colors"
                                                    >
                                                        {{ $option->name }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Venue picker --}}
                            <div>
                                <label class="block text-sm font-medium mb-1">Venue</label>
                                @if($venueSelectedName)
                                    <div class="flex items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-secondary)] px-3 py-2">
                                        <span class="flex-1 truncate">{{ $venueSelectedName }}</span>
                                        <button type="button" wire:click="clearVenue" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] shrink-0">&times;</button>
                                    </div>
                                @else
                                    <div x-data="{ open: false }" x-on:click.away="open = false" class="relative">
                                        <flux:input
                                            wire:model.live.debounce.300ms="venueSearch"
                                            placeholder="Search venues..."
                                            x-on:focus="open = true"
                                            x-on:input="open = true"
                                            autocomplete="off"
                                        />
                                        @if($this->venueOptions->isNotEmpty())
                                            <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg-primary)] shadow-lg max-h-60 overflow-y-auto">
                                                @foreach($this->venueOptions as $option)
                                                    <button
                                                        type="button"
                                                        wire:key="venue-{{ $option->id }}"
                                                        wire:click="selectVenue({{ $option->id }})"
                                                        x-on:click="open = false"
                                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--bg-secondary)] transition-colors"
                                                    >
                                                        {{ $option->name }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <flux:select wire:model="ownedBy" label="Owner">
                                <option value="">None</option>
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Tags">
                        <x-signals.tag-input :value="$tags" placeholder="Add tag..." x-on:tags-changed="$wire.set('tags', $event.detail)" />
                    </x-signals.form-section>

                    @if($groupedCustomFields->isNotEmpty())
                        <x-signals.form-section title="Custom Fields">
                            <x-signals.custom-fields-editor :groupedCustomFields="$groupedCustomFields" />
                        </x-signals.form-section>
                    @endif

                    {{-- Participants (#6) — only available once the opportunity exists --}}
                    {{-- (edit mode), so a member can be attached in a named role. Uses --}}
                    {{-- the SAME AddOpportunityParticipant action as the participants tab. --}}
                    @if($isEditing)
                        <x-signals.form-section title="Participants">
                            <div class="space-y-3">
                                {{-- Member picker --}}
                                <div>
                                    <label class="s-field-label mb-1 block">Member</label>
                                    @if($participantMemberSelectedName)
                                        <div class="flex items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-secondary)] px-3 py-2">
                                            <span class="flex-1 truncate">{{ $participantMemberSelectedName }}</span>
                                            <button type="button" wire:click="clearParticipantMember" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] shrink-0">&times;</button>
                                        </div>
                                    @else
                                        <div x-data="{ open: false }" x-on:click.away="open = false" class="relative">
                                            <flux:input
                                                wire:model.live.debounce.300ms="participantMemberSearch"
                                                placeholder="Search organisations or contacts..."
                                                x-on:focus="open = true"
                                                x-on:input="open = true"
                                                autocomplete="off"
                                            />
                                            @if($this->participantMemberOptions->isNotEmpty())
                                                <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg-primary)] shadow-lg max-h-60 overflow-y-auto">
                                                    @foreach($this->participantMemberOptions as $option)
                                                        <button
                                                            type="button"
                                                            wire:key="participant-option-{{ $option->id }}"
                                                            wire:click="selectParticipantMember({{ $option->id }})"
                                                            x-on:click="open = false"
                                                            class="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--bg-secondary)] transition-colors"
                                                        >
                                                            {{ $option->name }}
                                                            <span class="text-[var(--text-muted)]">· {{ $option->membership_type->label() }}</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                    @error('participantMemberId') <p class="mt-1 text-sm text-[var(--red)]">{{ $message }}</p> @enderror
                                </div>

                                {{-- Role (free-text, suggested set via datalist) --}}
                                <div>
                                    <flux:input
                                        wire:model="participantRole"
                                        label="Role"
                                        placeholder="e.g. Primary contact"
                                        list="form-participant-role-suggestions"
                                        maxlength="100"
                                    />
                                    <datalist id="form-participant-role-suggestions">
                                        @foreach(self::PARTICIPANT_SUGGESTED_ROLES as $suggested)
                                            <option value="{{ $suggested }}"></option>
                                        @endforeach
                                    </datalist>
                                </div>

                                <div class="flex items-center justify-between gap-3">
                                    <flux:checkbox wire:model="participantMute" label="Mute" />
                                    <flux:button type="button" wire:click="addParticipant" variant="primary" size="sm">Add participant</flux:button>
                                </div>

                                @if($this->participants->isNotEmpty())
                                    <ul class="divide-y divide-[var(--border)] rounded-lg border border-[var(--border)]">
                                        @foreach($this->participants as $participant)
                                            <li wire:key="form-participant-{{ $participant->id }}" class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                                <span class="min-w-0 flex-1 truncate">
                                                    {{ $participant->member?->name ?? '—' }}
                                                    @if($participant->role)<span class="text-[var(--text-muted)]"> · {{ $participant->role }}</span>@endif
                                                    @if($participant->mute)<span class="s-badge s-badge-amber ml-1">Muted</span>@endif
                                                </span>
                                                <button type="button"
                                                    wire:click="removeParticipant({{ $participant->id }})"
                                                    wire:confirm="Remove this participant?"
                                                    class="text-[var(--text-muted)] hover:text-[var(--red)] shrink-0">Remove</button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </x-signals.form-section>
                    @endif
                </div>
            </div>

            {{-- Add-new-address modal (C-data-2). Creates an Address on the --}}
            {{-- selected member via the shared CreateAddress action, then selects --}}
            {{-- it into the delivery/collection FK that opened the modal. --}}
            <flux:modal wire:model.self="showAddressModal" class="w-full max-w-lg">
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg">Add {{ $addressTarget === 'collection' ? 'Collection' : 'Delivery' }} Address</flux:heading>
                        <flux:subheading>This address is saved on the selected member.</flux:subheading>
                    </div>

                    <flux:input wire:model="newAddressName" label="Label" placeholder="e.g. Site Entrance" />
                    <flux:textarea wire:model="newAddressStreet" label="Street" rows="2" />
                    <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                        <flux:input wire:model="newAddressCity" label="City" />
                        <flux:input wire:model="newAddressCounty" label="County / State" />
                    </div>
                    <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                        <flux:input wire:model="newAddressPostcode" label="Postcode / ZIP" />
                        <flux:select wire:model="newAddressCountryId" label="Country">
                            <option value="">Select country...</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}" wire:key="new-addr-country-{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:select wire:model="newAddressTypeId" label="Type">
                        <option value="">Select type...</option>
                        @foreach($addressTypes as $type)
                            <option value="{{ $type->id }}" wire:key="new-addr-type-{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </flux:select>

                    @if($what3wordsConfigured)
                        <flux:input wire:model="newAddressWhat3words" label="what3words" placeholder="e.g. filled.count.soap" />
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <flux:button type="button" variant="ghost" wire:click="$set('showAddressModal', false)">Cancel</flux:button>
                        <flux:button type="button" variant="primary" wire:click="saveNewAddress">Add Address</flux:button>
                    </div>
                </div>
            </flux:modal>
        </form>
    </div>
</section>
