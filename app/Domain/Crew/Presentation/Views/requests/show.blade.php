<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ $this->request->title }}</flux:heading>
            <flux:text>{{ $this->request->project?->name }} · {{ $this->request->status->label() }}</flux:text>
            <flux:text>
                {{ app(\App\Support\Timezone::class)->toZone($this->request->starts_at, $this->request->timezone)->format('Y-m-d H:i') }}
                –
                {{ app(\App\Support\Timezone::class)->toZone($this->request->ends_at, $this->request->timezone)->format('Y-m-d H:i') }}
                · {{ $this->request->timezone }}</flux:text>
        </div>
        @can('publish', $this->request)
            <flux:button
                variant="primary"
                wire:click="publish"
                wire:confirm="Publish this staffing request?"
                wire:loading.attr="disabled"
            >
                Publish request
            </flux:button>
        @endcan

    </div>
    @include('crew::partials.errors')

    <section class="space-y-3">
        <flux:heading>Role coverage</flux:heading>
        @forelse($this->request->lines as $line)
            <div
                wire:key="line-{{ $line->id }}"
                class="flex flex-wrap justify-between gap-3 border-b py-2 text-sm"
            >
                <span>{{ $line->role->name }}</span>
                <span>{{ $line->filled_quantity }}/{{ $line->quantity }} filled · {{ $line->remainingQuantity() }}
                    remaining</span>
            </div>
        @empty
            <flux:text>Add a role line before publishing.</flux:text>
        @endforelse

        @can('update', $this->request)
            <form
                wire:submit="addLine"
                class="grid max-w-2xl items-end gap-3 sm:grid-cols-3"
            >
                <flux:select
                    label="Role"
                    wire:model="crew_role_id"
                >
                    <option value="">Choose role</option>
                    @foreach ($this->roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach

                </flux:select>
                <flux:input
                    type="number"
                    min="1"
                    label="Quantity"
                    wire:model="quantity"
                />
                <flux:button
                    type="submit"
                    wire:loading.attr="disabled"
                >
                    Add role line
                </flux:button>
            </form>
        @endcan

    </section>
    @can('crew.assignments.create')
        <section class="space-y-3 border-t pt-4">
            <flux:heading>Propose a crew member</flux:heading>
            <flux:text>Availability, requirements and duplicate assignments are checked before saving.</flux:text>
            <form
                wire:submit="propose"
                class="grid max-w-3xl gap-3 sm:grid-cols-2"
            >
                <flux:select
                    label="Request line"
                    wire:model.live="line_id"
                >
                    <option value="">Choose line</option>
                    @foreach ($this->request->lines as $line)
                        <option value="{{ $line->id }}">{{ $line->role->name }} · {{ $line->remainingQuantity() }}
                            remaining</option>
                    @endforeach

                </flux:select>
                <flux:select
                    label="Crew member"
                    wire:model="crew_member_id"
                >
                    <option value="">Choose member</option>
                    @foreach ($this->members as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach

                </flux:select>
                <flux:input
                    label="Currency"
                    wire:model="currency_code"
                    maxlength="3"
                />
                @can('crew.conflicts.override')
                    <flux:input
                        label="Warning override reason (when needed)"
                        wire:model="override_reason"
                    />
                @endcan

                <flux:button
                    type="submit"
                    wire:loading.attr="disabled"
                >
                    Propose assignment
                </flux:button>
            </form>
            @can('crew.view')
                @if ($line_id)
                    <div class="space-y-2">
                        <flux:heading size="sm">Ranked candidates</flux:heading>
                        @forelse($this->candidates as $candidate)
                            <div
                                wire:key="candidate-{{ $candidate->crew_member_id }}"
                                class="rounded border p-3 text-sm"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span>{{ $candidate->name }} · Score {{ $candidate->score }}/100</span>
                                    @if ($candidate->blockers === [])
                                        <flux:button
                                            size="sm"
                                            wire:click="$set('crew_member_id',{{ $candidate->crew_member_id }})"
                                        >
                                            Choose candidate
                                        </flux:button>
                                    @endif

                                </div>
                                @foreach ($candidate->blockers as $blocker)
                                    <p class="text-red-700 dark:text-red-300">Blocked: {{ $blocker['message'] }}</p>
                                @endforeach

                                @foreach ($candidate->warnings as $warning)
                                    <p class="text-amber-700 dark:text-amber-300">Warning: {{ $warning['message'] }}</p>
                                @endforeach

                            </div>
                        @empty
                            <flux:text>No eligible members with this role. Add roles and qualifications to the crew registry.
                            </flux:text>
                        @endforelse

                    </div>
                @endif
            @endcan

        </section>
    @endcan

    @can('crew.assignments.view')
        <section class="space-y-3 border-t pt-4">
            <flux:heading>Assignments</flux:heading>
            @forelse($this->assignments as $assignment)
                <div
                    wire:key="assignment-{{ $assignment->id }}"
                    class="flex flex-wrap justify-between gap-3 text-sm"
                >
                    <a
                        class="underline"
                        href="{{ route('crew.assignments.show', $assignment) }}"
                        wire:navigate.hover
                    >
                        {{ $assignment->crewMember->name }} · {{ $assignment->line->role->name }}
                    </a>
                    <span>{{ $assignment->status->label() }}</span>
                </div>
            @empty
                <flux:text>No assignments yet.</flux:text>
            @endforelse

        </section>
    @endcan

    @can('cancel', $this->request)
        <form
            wire:submit="cancel"
            class="max-w-xl space-y-3 border-t pt-4"
        >
            <flux:input
                label="Cancellation reason"
                wire:model="cancellation_reason"
            />
            <flux:button
                type="submit"
                variant="danger"
                wire:confirm="Cancel this request and its active assignments?"
            >
                Cancel request
            </flux:button>
        </form>
    @endcan

</div>
