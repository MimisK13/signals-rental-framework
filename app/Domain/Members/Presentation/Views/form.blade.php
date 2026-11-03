<section class="w-full">
    @if($isEditing && $member)
        @include('members::partials.member-header', ['member' => $member, 'subpage' => 'Edit'])
        @include('members::partials.member-tabs', ['member' => $member, 'activeTab' => ''])
    @else
        <x-dense::page-header :title="'Create ' . (\App\Domain\Members\Enums\MembershipType::tryFrom($membershipType)?->label() ?? 'Member')">
            <x-slot:breadcrumbs>
                <a href="{{ route('members.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Members</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-dense::page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">

                {{-- ======================================== --}}
                {{-- LEFT COLUMN — Core fields --}}
                {{-- ======================================== --}}
                <div class="space-y-6">

                    {{-- Icon Upload (edit mode only) --}}
                    @if($isEditing && $member)
                        <x-signals.form-section title="Profile Image">
                            @if($isUserMember)
                                <div class="flex items-center gap-4">
                                    <x-signals.entity-icon :model="$member" :size="56" />
                                    <div class="flex flex-col gap-1">
                                        <p class="text-[12px] text-[var(--text-muted)]">Managed from the user's profile.</p>
                                        <a href="{{ route('settings.profile') }}" wire:navigate.hover class="text-[12px] text-[var(--link)] hover:underline">Edit in profile settings &rarr;</a>
                                    </div>
                                </div>
                            @else
                                <livewire:components.icon-upload :model="$member" :key="'icon-'.$member->id" />
                            @endif
                        </x-signals.form-section>
                    @endif

                    {{-- Name & Type --}}
                    <x-signals.form-section title="Basic Info">
                        <div class="space-y-3">
                            @if($isUserMember)
                                <div>
                                    <flux:input wire:model="name" label="Name" readonly disabled
                                        description="Managed from the user's profile" />
                                    <a href="{{ route('settings.profile') }}" wire:navigate.hover class="mt-1 inline-block text-[12px] text-[var(--link)] hover:underline">Edit in profile settings &rarr;</a>
                                </div>
                            @else
                                <flux:input wire:model="name" label="Name" required />
                            @endif

                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model.live="membershipType" label="Membership Type" required :disabled="$isEditing">
                                    @if($isUserMember)
                                        <option value="{{ \App\Domain\Members\Enums\MembershipType::User->value }}">{{ \App\Domain\Members\Enums\MembershipType::User->label() }}</option>
                                    @endif
                                    @foreach($membershipTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </flux:select>

                                @if($isContact)
                                    <flux:input wire:model="title" label="Title" placeholder="e.g. Mr, Mrs, Dr" />
                                @endif
                            </div>

                            @if($isContact)
                                <flux:input wire:model="department" label="Department" />
                            @endif

                            <flux:textarea wire:model="description" label="Description" rows="2" />
                        </div>
                    </x-signals.form-section>

                    {{-- Account & Billing (Org/Venue) --}}
                    @if($isOrg)
                        <x-signals.form-section title="Account">
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                    <flux:input wire:model="accountNumber" label="Account Number" />
                                    <flux:select wire:model="ratingId" label="Rating">
                                        <option value="">None</option>
                                        @foreach($ratings as $rating)
                                            <option value="{{ $rating->sort_order }}">{{ $rating->name }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>

                                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                    <div>
                                        <flux:input wire:model="taxNumber" label="Tax Number" />
                                        @if($isEditing && filled($taxNumber))
                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                @if($taxNumberValidated === null)
                                                    <span class="s-badge s-badge-amber">Pending validation</span>
                                                @elseif($taxNumberValidated)
                                                    <span class="s-badge s-badge-green">Valid</span>
                                                @else
                                                    <span class="s-badge s-badge-red">Invalid</span>
                                                @endif
                                                <flux:button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    wire:click="recheckVatValidation"
                                                    wire:loading.attr="disabled"
                                                >
                                                    <span wire:loading.remove wire:target="recheckVatValidation">Re-check VAT</span>
                                                    <span wire:loading wire:target="recheckVatValidation">Checking…</span>
                                                </flux:button>
                                            </div>
                                        @endif
                                    </div>
                                    <flux:select wire:model="ownedBy" label="Owner">
                                        <option value="">None</option>
                                        @foreach($ownerOptions as $ownerMember)
                                            <option value="{{ $ownerMember->id }}">{{ $ownerMember->name }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>

                                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                    <flux:select wire:model="lawfulBasisTypeId" label="Legal Basis for Processing">
                                        <option value="">None</option>
                                        @foreach($lawfulBasisTypes as $basis)
                                            <option value="{{ $basis->id }}">{{ $basis->name }}</option>
                                        @endforeach
                                    </flux:select>
                                    <flux:input wire:model.number="invoiceTermLength" label="Invoice Term Length" type="number" min="0" />
                                </div>

                                <div class="flex items-center gap-6 pt-1">
                                    <flux:checkbox wire:model="isActive" label="Active" />
                                    <flux:checkbox wire:model="isCash" label="Cash Customer" />
                                    <flux:checkbox wire:model="isOnStop" label="On Stop" />
                                </div>
                            </div>
                        </x-signals.form-section>

                        <x-signals.form-section title="Tax & Pricing">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="saleTaxClassId" label="Sale Tax Class">
                                    <option value="">None</option>
                                    @foreach($taxClasses as $taxClass)
                                        <option value="{{ $taxClass->id }}">{{ $taxClass->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="purchaseTaxClassId" label="Purchase Tax Class">
                                    <option value="">None</option>
                                    @foreach($taxClasses as $taxClass)
                                        <option value="{{ $taxClass->id }}">{{ $taxClass->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model.live="priceCategoryId" label="Price Category">
                                    <option value="">None</option>
                                    @foreach($priceCategories as $priceCategory)
                                        <option value="{{ $priceCategory->id }}">{{ $priceCategory->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="priceCategoryTierId" label="Price Category Tier">
                                    <option value="">Base pricing</option>
                                    @foreach($priceCategoryTierOptions as $tier)
                                        <option wire:key="tier-{{ $tier->id }}" value="{{ $tier->id }}">{{ $tier->label }} (×{{ $tier->multiplier }})</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="discountCategoryId" label="Discount Category">
                                    <option value="">None</option>
                                    @foreach($discountCategories as $discountCategory)
                                        <option value="{{ $discountCategory->id }}">{{ $discountCategory->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </x-signals.form-section>
                    @else
                        {{-- Non-org: Active + Legal basis --}}
                        <x-signals.form-section title="Status">
                            <div class="space-y-3">
                                <div>
                                    <flux:checkbox wire:model="isActive" label="Active" :disabled="$isUserMember" />
                                    @if($isUserMember)
                                        <p class="mt-1 text-[12px] text-[var(--text-muted)]">A user's active state is managed from their user account.</p>
                                    @endif
                                </div>
                                <flux:select wire:model="lawfulBasisTypeId" label="Legal Basis for Processing">
                                    <option value="">None</option>
                                    @foreach($lawfulBasisTypes as $basis)
                                        <option value="{{ $basis->id }}">{{ $basis->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </x-signals.form-section>
                    @endif

                    {{-- Resource (bookable members) --}}
                    <x-signals.form-section title="Resource">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <div class="flex items-center gap-6 pt-1">
                                    <flux:checkbox wire:model.live="bookable" label="Bookable" />
                                </div>
                                <flux:select wire:model="locationTypeId" label="Location Type" :disabled="$isUserMember"
                                    :description="$isUserMember ? 'Managed from the user profile' : null">
                                    <option value="">None</option>
                                    @foreach($locationTypes as $lt)
                                        <option value="{{ $lt->sort_order }}">{{ $lt->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            @if($bookable)
                                <div class="grid grid-cols-4 gap-4 max-sm:grid-cols-2">
                                    <flux:input wire:model.number="dayCost" label="Day Cost" type="number" min="0" />
                                    <flux:input wire:model.number="hourCost" label="Hour Cost" type="number" min="0" />
                                    <flux:input wire:model.number="distanceCost" label="Distance Cost" type="number" min="0" />
                                    <flux:input wire:model.number="flatRateCost" label="Flat Rate" type="number" min="0" />
                                </div>
                            @endif
                        </div>
                    </x-signals.form-section>

                    {{-- Regional --}}
                    <x-signals.form-section title="Regional">
                        <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                            <flux:select wire:model="locale" label="Locale">
                                <option value="">None</option>
                                @foreach($locales as $loc)
                                    <option value="{{ $loc->name }}">{{ $loc->name }}</option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="defaultCurrencyCode" label="Default Currency">
                                <option value="">None</option>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->name }}">{{ $currency->name }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    </x-signals.form-section>

                    {{-- Actions --}}
                    <div class="flex items-center gap-4 pt-2">
                        <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create ' . (\App\Domain\Members\Enums\MembershipType::tryFrom($membershipType)?->label() ?? 'Member') }}</flux:button>
                        <flux:button variant="ghost" href="{{ $isEditing ? route('members.show', $memberId) : route('members.index') }}" wire:navigate.hover>Cancel</flux:button>
                    </div>
                </div>

                {{-- ======================================== --}}
                {{-- RIGHT COLUMN — Custom fields --}}
                {{-- ======================================== --}}
                <div class="space-y-6" style="position: sticky; top: 24px;">
                    <h2 style="font-family: var(--font-display); font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); padding-bottom: 4px; border-bottom: 1px solid var(--card-border);">Custom Fields</h2>

                    <x-signals.custom-fields-editor :groupedCustomFields="$groupedCustomFields" />
                </div>
            </div>
        </form>
    </div>
</section>
