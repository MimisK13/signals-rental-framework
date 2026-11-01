<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')

    <flux:heading size="xl">
        {{ $this->assignment->crewMember->name }} · {{ $this->assignment->line->role->name }}
    </flux:heading>
    <flux:text>
        {{ $this->assignment->project?->name }} · {{ $this->assignment->status->label() }}
    </flux:text>

    @include('crew::partials.errors')

    @if ($this->assignment->requires_realignment)
        <div
            role="status"
            class="rounded border border-amber-300 p-3 text-sm"
        >Project dates changed. Review and explicitly realign the shifts before continuing.</div>
    @endif

    @can('crew.rates.view')
        <div class="text-sm">Agreed rate: {{ $this->assignment->currency_code }}
            {{ $this->assignment->formatMoneyCost('rate_amount_minor') }} / {{ $this->assignment->rate_type->label() }} ·
            Estimate: {{ $this->assignment->formatMoneyCost('cost_estimate_minor') }}</div>
        @if (($this->assignment->cost_estimate_breakdown['cancellation']['amount_minor'] ?? 0) > 0)
            <p class="text-sm">Cancellation call minimum: {{ $this->assignment->currency_code }}
                {{ $this->assignment->formatMoneyCost('cost_estimate_minor') }}</p>
        @endif

    @endcan

    <div class="flex flex-wrap gap-2">
        @if ($this->assignment->status->value === 'proposed')
            @can('crew.assignments.create')
                <flux:button
                    wire:click="invite"
                    wire:confirm="Invite this crew member?"
                    wire:loading.attr="disabled"
                >Invite</flux:button>
            @endcan
        @endif

        @if ($this->assignment->status->value === 'invited')
            @can('confirm', $this->assignment)
                <flux:button
                    variant="primary"
                    wire:click="confirm"
                    wire:confirm="Confirm this assignment and freeze the agreed compensation?"
                    wire:loading.attr="disabled"
                >Confirm assignment</flux:button>
            @endcan
        @endif

    </div>
    @can('crew.shifts.manage')

        @if (in_array($this->assignment->status->value, ['proposed', 'invited', 'confirmed'], true))

            <section class="space-y-3 border-t pt-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading>Schedule realignment</flux:heading>
                    @if (!$realigning)
                        <flux:button wire:click="beginRealignment">Review shift realignment</flux:button>
                    @endif

                </div>
                @if ($realigning)
                    <flux:text>Review every active shift in {{ app(\App\Support\Timezone::class)->current() }}. Saving
                        rechecks conflicts and clears the schedule review flag.</flux:text>
                    <flux:text>Allowed period:
                        {{ app(\App\Support\Timezone::class)->toLocal($this->assignment->line->effectiveStartsAt())->format('Y-m-d H:i') }}
                        –
                        {{ app(\App\Support\Timezone::class)->toLocal($this->assignment->line->effectiveEndsAt())->format('Y-m-d H:i') }}.
                    </flux:text>
                    <form
                        wire:submit="realign"
                        class="max-w-4xl space-y-3"
                    >
                        @forelse($realignmentShifts as $index => $row)
                            <div
                                wire:key="realignment-{{ $row['id'] }}"
                                class="grid gap-3 rounded border p-3 sm:grid-cols-2"
                            >
                                <flux:input
                                    label="Shift {{ $loop->iteration }} starts"
                                    type="datetime-local"
                                    wire:model="realignmentShifts.{{ $index }}.starts_at"
                                    required
                                />
                                <flux:input
                                    label="Shift {{ $loop->iteration }} ends"
                                    type="datetime-local"
                                    wire:model="realignmentShifts.{{ $index }}.ends_at"
                                    required
                                />
                            </div>
                        @empty

                            <flux:text>No active shifts to realign. Add a shift before submitting a schedule review.
                            </flux:text>
                        @endforelse

                        <flux:input
                            label="Reason for realignment"
                            wire:model="realignment_reason"
                            required
                        />
                        @can('crew.conflicts.override')
                            <flux:input
                                label="Warning override reason (when needed)"
                                wire:model="override_reason"
                            />
                        @endcan

                        <div class="flex flex-wrap gap-2">
                            <flux:button
                                type="submit"
                                variant="primary"
                                wire:confirm="Apply these reviewed shift dates and clear the schedule review flag?"
                                wire:loading.attr="disabled"
                            >Apply reviewed schedule</flux:button>
                            <flux:button wire:click="cancelRealignment">Cancel review</flux:button>
                        </div>
                    </form>
                @endif

            </section>
        @endif

    @endcan

    @can('crew.shifts.view')
        <section class="space-y-3 border-t pt-4">
            <flux:heading>Shifts · {{ app(\App\Support\Timezone::class)->current() }}</flux:heading>
            @forelse($this->shifts as $shift)
                <div
                    wire:key="shift-{{ $shift->id }}"
                    class="flex flex-wrap items-center justify-between gap-3 border-b py-2 text-sm"
                >
                    <div>{{ $shift->type->label() }} ·
                        {{ app(\App\Support\Timezone::class)->toLocal($shift->starts_at)->format('Y-m-d H:i') }} –
                        {{ app(\App\Support\Timezone::class)->toLocal($shift->ends_at)->format('Y-m-d H:i') }}<p>
                            {{ $shift->status->label() }} · {{ $shift->break_minutes }} minute break
                            @if ($shift->is_overnight)
                                · Overnight
                            @endif

                        </p>
                    </div>
                    <div class="flex gap-2">
                        @can('confirm', $shift)
                            <flux:button
                                size="sm"
                                wire:click="confirmShift({{ $shift->id }})"
                            >Confirm shift</flux:button>
                        @endcan

                        @can('start', $shift)
                            <flux:button
                                size="sm"
                                wire:click="startShift({{ $shift->id }})"
                            >Start</flux:button>
                        @endcan

                        @can('complete', $shift)
                            <flux:button
                                size="sm"
                                wire:click="completeShift({{ $shift->id }})"
                            >Complete</flux:button>
                        @endcan

                        @can('update', $shift)
                            <flux:dropdown>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="ellipsis-horizontal"
                                    aria-label="Shift actions"
                                />
                                <flux:menu>
                                    <flux:menu.item wire:click="editShift({{ $shift->id }})">Edit shift</flux:menu.item>
                                    <flux:menu.item
                                        variant="danger"
                                        wire:click="cancelShift({{ $shift->id }})"
                                        wire:confirm="Cancel this shift? Enter the cancellation reason below first."
                                    >Cancel shift</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endcan

                    </div>
                </div>
            @empty
                <flux:text>No shifts planned.</flux:text>
            @endforelse

        </section>
    @endcan

    @can('create', \App\Domain\Crew\Models\CrewShift::class)
        <form
            wire:submit="addShift"
            class="grid max-w-3xl gap-3 sm:grid-cols-2"
        >
            <flux:input
                label="Starts ({{ app(\App\Support\Timezone::class)->current() }})"
                type="datetime-local"
                wire:model="starts_at"
            />
            <flux:input
                label="Ends"
                type="datetime-local"
                wire:model="ends_at"
            />
            <flux:select
                label="Shift type"
                wire:model="type"
            >
                @foreach (\App\Domain\Crew\Enums\CrewShiftType::cases() as $kind)
                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                @endforeach

            </flux:select>
            <flux:input
                label="Unpaid break (minutes)"
                type="number"
                min="0"
                wire:model="break_minutes"
            />
            <flux:input
                label="Reason for shift cancellation (when needed)"
                wire:model="reason"
            />
            <flux:button
                type="submit"
                wire:loading.attr="disabled"
            >{{ $editingShiftId ? 'Save shift changes' : 'Add shift' }}</flux:button>
            @if ($editingShiftId)
                <flux:button wire:click="cancelShiftEdit">Cancel edit</flux:button>
            @endif

        </form>
    @endcan

    @can('crew.timesheets.view')
        <section class="space-y-3 border-t pt-4">
            <flux:heading>Timesheets</flux:heading>
            @forelse($this->timesheets as $timesheet)
                <div
                    wire:key="timesheet-{{ $timesheet->id }}"
                    class="text-sm"
                >
                    <a
                        class="underline"
                        href="{{ route('crew.timesheets.show', $timesheet) }}"
                        wire:navigate.hover
                    >{{ $timesheet->period_start->format('Y-m-d') }} – {{ $timesheet->period_end->format('Y-m-d') }}</a> ·
                    {{ $timesheet->status->label() }}
                </div>
            @empty
                <flux:text>No timesheets yet.</flux:text>
            @endforelse

        </section>
    @endcan

    @can('create', \App\Domain\Crew\Models\CrewTimesheet::class)
        <form
            wire:submit="createTimesheet"
            class="grid max-w-3xl items-end gap-3 sm:grid-cols-3"
        >
            <flux:input
                label="Period start"
                type="date"
                wire:model="period_start"
            />
            <flux:input
                label="Period end"
                type="date"
                wire:model="period_end"
            />
            <flux:button
                type="submit"
                wire:loading.attr="disabled"
            >Create timesheet</flux:button>
        </form>
    @endcan

    @can('substitute', $this->assignment)

        <section class="space-y-3 border-t pt-4">
            <flux:heading>Substitute crew member</flux:heading>
            @if (auth()->user()->can('crew.assignments.create') && auth()->user()->can('crew.assignments.confirm'))
                <flux:text>The original agreed compensation stays in history. The replacement receives a new assignment and
                    rate snapshot.</flux:text>
                <form
                    wire:submit="substitute"
                    class="grid max-w-3xl gap-3 sm:grid-cols-2"
                >
                    <flux:select
                        label="Replacement crew member"
                        wire:model="replacement_member_id"
                    >
                        <option value="">Choose replacement</option>
                        @foreach ($this->replacementMembers as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input
                        label="Reason for substitution"
                        wire:model="substitution_reason"
                        required
                    />
                    @can('crew.shifts.manage')
                        <flux:checkbox
                            label="Copy existing shifts to replacement"
                            wire:model="copy_shifts"
                        />
                    @else
                        <flux:text>Existing shifts will be cancelled. Shift management permission is required to copy them to
                            the replacement.</flux:text>
                    @endcan

                    @can('crew.conflicts.override')
                        <flux:input
                            label="Warning override reason (when needed)"
                            wire:model="override_reason"
                        />
                    @endcan

                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:confirm="Replace this crew member and confirm a new assignment? The original assignment and shifts remain in history."
                        wire:loading.attr="disabled"
                    >Confirm substitution</flux:button>
                </form>
            @else
                <flux:text>Substitution also requires permission to create and confirm assignments.</flux:text>
            @endif

        </section>
    @endcan

    @can('cancel', $this->assignment)
        <form
            wire:submit="cancel"
            class="max-w-xl space-y-3 border-t pt-4"
        >
            <flux:input
                label="Cancellation reason"
                wire:model="reason"
            />
            <flux:button
                type="submit"
                variant="danger"
                wire:confirm="Cancel this assignment and its pending shifts?"
            >Cancel assignment</flux:button>
        </form>
    @endcan

</div>
