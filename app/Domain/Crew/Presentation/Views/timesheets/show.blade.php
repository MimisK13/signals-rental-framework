<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')
    <flux:heading size="xl">Timesheet · {{ $this->timesheet->crewMember->name }}</flux:heading>
    <flux:text>{{ $this->timesheet->project?->name }} · {{ $this->timesheet->period_start->format('Y-m-d') }} –
        {{ $this->timesheet->period_end->format('Y-m-d') }} · {{ $this->timesheet->status->label() }}</flux:text>
    @include('crew::partials.errors')

    @if ($this->timesheet->rejection_reason)
        <div
            role="status"
            class="rounded border border-amber-300 p-3 text-sm"
        >
            Changes requested: {{ $this->timesheet->rejection_reason }}
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2">Kind</th>
                    <th class="p-2">Starts · {{ app(\App\Support\Timezone::class)->current() }}</th>
                    <th class="p-2">Ends</th>
                    <th class="p-2 text-right">Break</th>
                    <th class="p-2 text-right">Minutes</th>
                    @can('crew.rates.view')
                        <th class="p-2 text-right">Amount</th>
                    @endcan

                    <th class="p-2">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->timesheet->entries as $entry)
                    <tr
                        wire:key="entry-{{ $entry->id }}"
                        class="border-b"
                    >
                        <td class="p-2">{{ $entry->kind->label() }}</td>
                        <td class="p-2">
                            {{ app(\App\Support\Timezone::class)->toLocal($entry->started_at)->format('Y-m-d H:i') }}
                        </td>
                        <td class="p-2">
                            {{ app(\App\Support\Timezone::class)->toLocal($entry->ended_at)->format('Y-m-d H:i') }}
                        </td>
                        <td class="p-2 text-right">{{ $entry->break_minutes }}</td>
                        <td class="p-2 text-right">{{ $entry->minutes }}</td>
                        @can('crew.rates.view')
                            <td class="p-2 text-right">{{ $entry->formatMoneyCost('amount_minor') }}</td>
                        @endcan

                        <td class="p-2">
                            @can('update', $this->timesheet)
                                <flux:dropdown>
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="ellipsis-horizontal"
                                        aria-label="Entry actions"
                                    />
                                    <flux:menu>
                                        <flux:menu.item wire:click="editEntry({{ $entry->id }})">Edit entry
                                        </flux:menu.item>
                                        <flux:menu.item
                                            variant="danger"
                                            wire:click="removeEntry({{ $entry->id }})"
                                            wire:confirm="Remove this time entry?"
                                        >
                                            Remove entry
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endcan

                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="7"
                            class="p-6 text-center"
                        >
                            No actual time entries recorded.
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </div>
    <p class="text-sm tabular-nums">Total: {{ $this->timesheet->total_minutes }} minutes
        @can('crew.rates.view')
            · {{ $this->timesheet->formatMoneyCost('total_pay_minor') }}
        @endcan

    </p>
    @can('update', $this->timesheet)
        <form
            wire:submit="addEntry"
            class="grid max-w-3xl gap-3 sm:grid-cols-2"
        >
            <flux:input
                label="Starts ({{ app(\App\Support\Timezone::class)->current() }})"
                type="datetime-local"
                wire:model="started_at"
            />
            <flux:input
                label="Ends"
                type="datetime-local"
                wire:model="ended_at"
            />
            <flux:select
                label="Entry kind"
                wire:model="kind"
            >
                @foreach (\App\Domain\Crew\Enums\CrewTimeEntryKind::cases() as $entryKind)
                    <option value="{{ $entryKind->value }}">{{ $entryKind->label() }}</option>
                @endforeach

            </flux:select>
            <flux:input
                label="Unpaid break (minutes)"
                type="number"
                min="0"
                wire:model="break_minutes"
            />
            <flux:button
                type="submit"
                wire:loading.attr="disabled"
            >
                {{ $editingEntryId ? 'Save entry changes' : 'Add actual time' }}
            </flux:button>
            @if ($editingEntryId)
                <flux:button wire:click="cancelEntryEdit">Cancel edit</flux:button>
            @endif

        </form>
    @endcan

    <div class="flex flex-wrap gap-2">
        @can('submit', $this->timesheet)
            <flux:button
                variant="primary"
                wire:click="submit"
                wire:confirm="Submit this timesheet for approval?"
                wire:loading.attr="disabled"
            >
                Submit timesheet
            </flux:button>
        @endcan

        @can('approve', $this->timesheet)
            <flux:button
                variant="primary"
                wire:click="approve"
                wire:confirm="Approve this timesheet and freeze the totals?"
                wire:loading.attr="disabled"
            >
                Approve timesheet
            </flux:button>
        @endcan

        @can('lock', $this->timesheet)
            <flux:button
                wire:click="lock"
                wire:confirm="Lock this approved timesheet?"
                wire:loading.attr="disabled"
            >
                Lock timesheet
            </flux:button>
        @endcan

    </div>
    @can('reject', $this->timesheet)
        <form
            wire:submit="reject"
            class="max-w-xl space-y-3"
        >
            <flux:input
                label="Rejection reason"
                wire:model="reason"
            />
            <flux:button
                type="submit"
                variant="danger"
                wire:confirm="Reject this timesheet and return it for corrections?"
            >
                Reject timesheet
            </flux:button>
        </form>
    @endcan

    @if (in_array($this->timesheet->status->value, ['approved', 'locked'], true))
        <flux:text>Approved totals are frozen. Payment is handled separately by Finance.</flux:text>
    @endif

</div>
