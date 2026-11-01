<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')

    <div class="flex flex-wrap justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ $this->member->name }}</flux:heading>
            <flux:text>{{ $this->member->engagement_type->label() }} · {{ $this->member->status->label() }} ·
                {{ $this->member->home_timezone }}</flux:text>
        </div>
        @can('update', $this->member)
            <flux:button
                :href="route('crew.members.edit', $this->member)"
                wire:navigate.hover
            >
                Edit member
            </flux:button>
        @endcan

    </div>
    @include('crew::partials.errors')

    @can('viewSensitive', $this->member)
        <div class="text-sm">{{ $this->member->email }} {{ $this->member->phone }}<p>{{ $this->member->internal_notes }}</p>
        </div>
    @endcan

    <section class="space-y-3">
        <flux:heading>Roles and skills</flux:heading>
        <flux:text>{{ $this->member->roles->pluck('name')->join(', ') ?: 'No roles assigned.' }}</flux:text>
        <div class="flex flex-wrap gap-2">
            @forelse($this->member->skills as $skill)
                <flux:badge wire:key="skill-{{ $skill->id }}">{{ $skill->name }} · {{ $skill->pivot->proficiency }}
                </flux:badge>
            @empty
                <flux:text>No skills recorded.</flux:text>
            @endforelse

        </div>
        @can('update', $this->member)
            <form
                wire:submit="addSkill"
                class="grid max-w-2xl items-end gap-3 sm:grid-cols-3"
            >
                <flux:select
                    label="Skill"
                    wire:model="skill_id"
                >
                    <option value="">Choose skill</option>
                    @foreach ($this->skills as $skill)
                        <option value="{{ $skill->id }}">{{ $skill->name }}</option>
                    @endforeach

                </flux:select>
                <flux:select
                    label="Proficiency"
                    wire:model="proficiency"
                >
                    @foreach (\App\Domain\Crew\Enums\CrewProficiency::cases() as $level)
                        <option value="{{ $level->value }}">{{ $level->label() }}</option>
                    @endforeach

                </flux:select>
                <flux:button
                    type="submit"
                    wire:loading.attr="disabled"
                >
                    Save skill
                </flux:button>
            </form>
        @endcan

    </section>
    @can('update', $this->member)
        <form
            wire:submit="addRole"
            class="grid max-w-3xl items-end gap-3 sm:grid-cols-3"
        >
            <flux:select
                label="Role"
                wire:model="role_id"
            >
                <option value="">Choose role</option>
                @foreach ($this->roles as $role)
                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                @endforeach

            </flux:select>
            <flux:checkbox
                label="Make primary role"
                wire:model="is_primary"
            />
            <flux:button
                type="submit"
                wire:loading.attr="disabled"
            >
                Save role
            </flux:button>
        </form>
    @endcan

    <section class="space-y-3 border-t pt-4">
        <flux:heading>Qualifications</flux:heading>
        @forelse($this->member->qualifications as $qualification)
            <div
                wire:key="qualification-{{ $qualification->id }}"
                class="flex flex-wrap items-center gap-3 text-sm"
            >
                <span>{{ $qualification->qualification->name }}</span>
                <span>{{ $qualification->status()->label() }}</span>
                <span>Expires {{ $qualification->expires_at?->format('Y-m-d') ?? 'No expiry' }}</span>
                @if (!$qualification->verified_at)
                    @can('crew.settings.manage')
                        <flux:button
                            size="sm"
                            wire:click="verifyQualification({{ $qualification->id }})"
                            wire:confirm="Verify this qualification?"
                        >
                            Verify
                        </flux:button>
                    @endcan
                @endif

            </div>
        @empty
            <flux:text>No qualifications recorded.</flux:text>
        @endforelse

        @can('update', $this->member)
            <form
                wire:submit="addQualification"
                class="grid max-w-3xl gap-3 sm:grid-cols-2"
            >
                <flux:select
                    label="Qualification"
                    wire:model="crew_qualification_id"
                >
                    <option value="">Choose qualification</option>
                    @foreach ($this->qualificationTypes as $qualificationType)
                        <option value="{{ $qualificationType->id }}">{{ $qualificationType->name }}</option>
                    @endforeach

                </flux:select>
                <flux:input
                    label="Certificate reference"
                    wire:model="reference_number"
                />
                <flux:input
                    label="Issued date"
                    type="date"
                    wire:model="issued_at"
                />
                <flux:input
                    label="Expiry date"
                    type="date"
                    wire:model="expires_at"
                />
                <flux:button
                    type="submit"
                    wire:loading.attr="disabled"
                >
                    Add qualification
                </flux:button>
            </form>
        @endcan

    </section>
    @can('crew.availability.view')
        <section class="space-y-3 border-t pt-4">
            <flux:heading>Availability</flux:heading>
            @forelse($this->availability as $block)
                <div
                    wire:key="availability-{{ $block->id }}"
                    class="flex flex-wrap items-center gap-3 text-sm"
                >
                    <span>{{ app(\App\Support\Timezone::class)->toLocal($block->starts_at)->format('Y-m-d H:i') }} –
                        {{ app(\App\Support\Timezone::class)->toLocal($block->ends_at)->format('Y-m-d H:i') }}</span>
                    <span>{{ $block->status->label() }}</span>
                    @can('approve', $block)
                        <flux:button
                            size="sm"
                            wire:click="approveAvailability({{ $block->id }})"
                        >
                            Approve
                        </flux:button>
                    @endcan

                </div>
            @empty
                <flux:text>No upcoming availability blocks.</flux:text>
            @endforelse

            @can('create', \App\Domain\Crew\Models\CrewAvailability::class)
                <form
                    wire:submit="requestAvailability"
                    class="grid max-w-3xl gap-3 sm:grid-cols-2"
                >
                    <flux:input
                        label="From ({{ app(\App\Support\Timezone::class)->current() }})"
                        type="datetime-local"
                        wire:model="starts_at"
                        required
                    />
                    <flux:input
                        label="Until"
                        type="datetime-local"
                        wire:model="ends_at"
                        required
                    />
                    <flux:input
                        label="Reason"
                        wire:model="reason"
                    />
                    <flux:button
                        type="submit"
                        wire:loading.attr="disabled"
                    >
                        Request unavailability
                    </flux:button>
                </form>
            @endcan

        </section>
    @endcan

    @can('crew.assignments.view')
        <section class="space-y-3 border-t pt-4">
            <flux:heading>Recent assignments</flux:heading>
            @forelse($this->assignments as $assignment)
                <div
                    wire:key="assignment-{{ $assignment->id }}"
                    class="flex flex-wrap justify-between gap-3 text-sm"
                >
                    <a
                        href="{{ route('crew.assignments.show', $assignment) }}"
                        wire:navigate.hover
                        class="underline"
                    >
                        {{ $assignment->project?->name ?? 'Assignment #' . $assignment->id }}
                    </a>
                    <span>{{ $assignment->status->label() }}</span>
                </div>
            @empty
                <flux:text>No assignments yet.</flux:text>
            @endforelse

        </section>
    @endcan

</div>
