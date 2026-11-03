<section class="w-full">
    @include('members::partials.member-header', ['member' => $member, 'subpage' => 'Add Relationship'])
    @include('members::partials.member-tabs', ['member' => $member, 'activeTab' => 'contacts'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save" class="max-w-2xl space-y-8">
            <x-signals.form-section title="Relationship Details">
                <div class="space-y-4">
                    <x-dense::field :label="$isContact ? 'Related Member' : 'Contact'" required>
                        {{-- Brand-accent highlight: the shared .s-combobox CSS uses the
                             hardcoded --green; re-point this instance to the themeable
                             brand accent (--blue) used by other inputs. --}}
                        <div class="s-relationship-combobox">
                            <x-signals.combobox
                                name="relatedMemberId"
                                :value="$relatedMemberId"
                                :placeholder="$isContact ? 'Search organisations, venues, contacts...' : 'Search contacts...'"
                                :options="$eligibleMembers->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->values()->all()"
                                x-on:combobox-selected.window="if ($event.detail.name === 'relatedMemberId') $wire.set('relatedMemberId', $event.detail.value)"
                            />
                        </div>
                    </x-dense::field>
                    <flux:input wire:model="relationshipType" label="Relationship Type" placeholder="e.g. Employee, Contractor, Director" />
                    @if($isContact)
                        <flux:checkbox wire:model="isPrimary" label="Primary organisation" />
                    @endif
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Add Relationship</flux:button>
                <flux:button variant="ghost" href="{{ route('members.show', $member) }}" wire:navigate.hover>Cancel</flux:button>
            </div>
        </form>
    </div>

    {{-- Override the shared combobox's hardcoded green focus/selected colours
         with the themeable brand accent, scoped to this form only. --}}
    <style>
        .s-relationship-combobox .s-combobox-input:focus { border-color: var(--blue); }
        .s-relationship-combobox .s-combobox-option.selected { color: var(--blue-ink); }
    </style>
</section>
