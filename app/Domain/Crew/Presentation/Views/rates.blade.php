<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')
    <flux:heading size="xl">Crew rate cards</flux:heading>
    <flux:text>Effective dated defaults. Confirmed assignments retain their agreed compensation.</flux:text>
    @include('crew::partials.errors')

    @can('create', \App\Domain\Crew\Models\CrewRateCard::class)
        <form
            wire:submit="save"
            class="grid max-w-4xl gap-3 sm:grid-cols-2 lg:grid-cols-3"
        >
            <flux:select
                label="Crew member"
                wire:model="crew_member_id"
            >
                <option value="">Any member</option>
                @foreach ($this->members as $member)
                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                @endforeach

            </flux:select>
            <flux:select
                label="Role"
                wire:model="crew_role_id"
            >
                <option value="">Any role</option>
                @foreach ($this->roles as $role)
                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                @endforeach

            </flux:select>
            <flux:input
                label="Currency"
                maxlength="3"
                wire:model="currency_code"
            />
            <flux:input
                label="Effective from"
                type="date"
                wire:model="effective_from"
            />
            <flux:input
                label="Effective until"
                type="date"
                wire:model="effective_to"
            />
            <flux:input
                label="Hourly amount"
                inputmode="decimal"
                wire:model="hourly_rate"
            />
            <flux:input
                label="Daily amount"
                inputmode="decimal"
                wire:model="daily_rate"
            />
            <flux:input
                label="Fixed amount"
                inputmode="decimal"
                wire:model="fixed_rate"
            />
            <flux:button
                type="submit"
                variant="primary"
                wire:loading.attr="disabled"
            >
                {{ $editingRateId ? 'Save rate changes' : 'Add rate card' }}
            </flux:button>
            @if ($editingRateId)
                <flux:button wire:click="cancelEdit">Cancel edit</flux:button>
            @endif

        </form>
    @endcan

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2">Member / role</th>
                    <th class="p-2">Effective period</th>
                    <th class="p-2 text-right">Hourly</th>
                    <th class="p-2 text-right">Daily</th>
                    <th class="p-2 text-right">Fixed</th>
                    <th class="p-2">Status</th>
                    <th class="w-12 p-2">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->rates as $rate)
                    <tr
                        wire:key="rate-{{ $rate->id }}"
                        class="border-b"
                    >
                        <td class="p-2">{{ $rate->crewMember?->name ?? 'Any member' }} ·
                            {{ $rate->role?->name ?? 'Any role' }}</td>
                        <td class="p-2">{{ $rate->effective_from->format('Y-m-d') }} –
                            {{ $rate->effective_to?->format('Y-m-d') ?? 'Open ended' }}</td>
                        <td class="p-2 text-right tabular-nums">
                            {{ $rate->hourly_rate_minor !== null ? $rate->formatMoneyCost('hourly_rate_minor') : '—' }}
                            {{ $rate->currency_code }}</td>
                        <td class="p-2 text-right tabular-nums">
                            {{ $rate->daily_rate_minor !== null ? $rate->formatMoneyCost('daily_rate_minor') : '—' }}
                        </td>
                        <td class="p-2 text-right tabular-nums">
                            {{ $rate->fixed_rate_minor !== null ? $rate->formatMoneyCost('fixed_rate_minor') : '—' }}
                        </td>
                        <td class="p-2">{{ $rate->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="p-2">
                            @if ($rate->is_active)
                                @can('update', $rate)
                                    <flux:dropdown>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="ellipsis-horizontal"
                                            aria-label="Rate actions"
                                        />
                                        <flux:menu>
                                            <flux:menu.item wire:click="edit({{ $rate->id }})">Edit</flux:menu.item>
                                            <flux:menu.item
                                                wire:click="deactivate({{ $rate->id }})"
                                                wire:confirm="Deactivate this rate card? Agreed assignments keep their existing compensation."
                                            >
                                                Deactivate
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endcan
                            @endif

                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="7"
                            class="p-6 text-center"
                        >
                            No rate cards configured.
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </div>{{ $this->rates->links() }}
</div>
