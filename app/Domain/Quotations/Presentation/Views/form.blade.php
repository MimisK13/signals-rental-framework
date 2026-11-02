<section class="relative min-w-0 w-full">
    <x-dense::page-header :title="$projectId ? 'New Project Quotation' : 'New Standalone Quotation'">
        <x-slot:actions>
            <x-dense::button :href="route('quotations.index')" variant="ghost" size="sm" wire:navigate.hover>Cancel</x-dense::button>
            <x-dense::button type="submit" form="quotation-form" variant="accent" size="sm"
                wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Create Quotation</span>
                <span wire:loading wire:target="save">Creating…</span>
            </x-dense::button>
        </x-slot:actions>
    </x-dense::page-header>

    <form id="quotation-form" wire:submit="save" class="px-6 pb-6 max-md:px-5 max-sm:px-3">
        <div class="mx-auto max-w-5xl space-y-6">
            <x-dense::card title="Commercial context">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-dense::field label="Project" for="quotation-project" :error="$errors->first('projectId')">
                        <x-dense::select id="quotation-project" wire:model="projectId">
                            <option value="">Standalone quotation</option>
                            @forelse($projects as $projectOption)
                                <option value="{{ $projectOption->id }}">{{ $projectOption->name }}</option>
                            @empty
                            @endforelse
                        </x-dense::select>
                    </x-dense::field>
                    <x-dense::field label="Store" for="quotation-store" required :error="$errors->first('storeId')">
                        <x-dense::select id="quotation-store" wire:model="storeId" required>
                            <option value="">Choose a Store</option>
                            @forelse($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->name }}</option>
                            @empty
                            @endforelse
                        </x-dense::select>
                    </x-dense::field>
                    <x-dense::field label="Subject" for="quotation-subject" required :error="$errors->first('subject')">
                        <x-dense::input id="quotation-subject" wire:model="subject" required />
                    </x-dense::field>
                    <x-dense::field label="Reference" for="quotation-reference" :error="$errors->first('reference')">
                        <x-dense::input id="quotation-reference" wire:model="reference" />
                    </x-dense::field>
                    <x-dense::field label="Customer" for="quotation-customer" required :error="$errors->first('memberId')">
                        <x-dense::select id="quotation-customer" wire:model="memberId" required>
                            <option value="">Choose a customer</option>
                            @forelse($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @empty
                            @endforelse
                        </x-dense::select>
                    </x-dense::field>
                    <x-dense::field label="Venue" for="quotation-venue" :error="$errors->first('venueId')">
                        <x-dense::select id="quotation-venue" wire:model="venueId">
                            <option value="">None</option>
                            @forelse($venues as $venue)
                                <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                            @empty
                            @endforelse
                        </x-dense::select>
                    </x-dense::field>
                    <x-dense::field label="Owner" for="quotation-owner" :error="$errors->first('ownerId')">
                        <x-dense::select id="quotation-owner" wire:model="ownerId">
                            <option value="">None</option>
                            @forelse($owners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @empty
                            @endforelse
                        </x-dense::select>
                    </x-dense::field>
                    <x-dense::field label="Currency" for="quotation-currency" required :error="$errors->first('currencyCode')">
                        <x-dense::input id="quotation-currency" wire:model="currencyCode" maxlength="3" required />
                    </x-dense::field>
                </div>
            </x-dense::card>

            <x-dense::card title="Schedule and terms">
                <div class="grid gap-4 md:grid-cols-3">
                    <x-dense::field label="Starts" for="quotation-starts" :error="$errors->first('startsAt')">
                        <x-dense::input id="quotation-starts" type="datetime-local" wire:model="startsAt" />
                    </x-dense::field>
                    <x-dense::field label="Ends" for="quotation-ends" :error="$errors->first('endsAt')">
                        <x-dense::input id="quotation-ends" type="datetime-local" wire:model="endsAt" />
                    </x-dense::field>
                    <x-dense::field label="Valid until" for="quotation-valid" :error="$errors->first('validUntil')">
                        <x-dense::input id="quotation-valid" type="datetime-local" wire:model="validUntil" />
                    </x-dense::field>
                </div>
                <div class="mt-4">
                    <x-dense::field label="Description" for="quotation-description" :error="$errors->first('description')">
                        <x-dense::textarea id="quotation-description" wire:model="description" rows="4" />
                    </x-dense::field>
                </div>
            </x-dense::card>
        </div>
    </form>
</section>
