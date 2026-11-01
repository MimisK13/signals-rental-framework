<div x-on:activity-saved.window="$dispatch('close-modal', 'calendar-activity-form')">
    <x-signals.modal
        name="calendar-activity-form"
        :title="$activityId ? 'Edit Activity' : 'New Activity'"
        size="lg"
    >
        <form
            wire:submit="save"
            class="space-y-4"
        >
            <div>
                <label
                    class="s-field-label"
                    for="cal-form-subject"
                >Subject<span class="s-field-label-required">*</span></label>
                <input
                    type="text"
                    id="cal-form-subject"
                    wire:model="subject"
                    class="s-input"
                    placeholder="What needs doing?"
                    autocomplete="off"
                />
                @error('subject')
                    <div class="s-field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                <div>
                    <label class="s-field-label">Starts At</label>
                    <x-signals.datetime-input
                        wire:key="cal-form-starts-{{ $startsAt }}"
                        :value="$startsAt"
                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('startsAt', $event.detail)"
                    />
                    @error('starts_at')
                        <div class="s-field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="s-field-label">Ends At</label>
                    <x-signals.datetime-input
                        wire:key="cal-form-ends-{{ $endsAt }}"
                        :value="$endsAt"
                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('endsAt', $event.detail)"
                    />
                    @error('ends_at')
                        <div class="s-field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                <div>
                    <label
                        class="s-field-label"
                        for="cal-form-type"
                    >Type</label>
                    <select
                        id="cal-form-type"
                        wire:model="typeId"
                        class="s-select"
                    >
                        @foreach ($activityTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label
                        class="s-field-label"
                        for="cal-form-status"
                    >Status</label>
                    <select
                        id="cal-form-status"
                        wire:model="statusId"
                        class="s-select"
                    >
                        @foreach ($activityStatuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                <div>
                    <label
                        class="s-field-label"
                        for="cal-form-priority"
                    >Priority</label>
                    <select
                        id="cal-form-priority"
                        wire:model="priority"
                        class="s-select"
                    >
                        @foreach ($activityPriorities as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label
                        class="s-field-label"
                        for="cal-form-time-status"
                    >Time Status</label>
                    <select
                        id="cal-form-time-status"
                        wire:model="timeStatus"
                        class="s-select"
                    >
                        @foreach ($timeStatuses as $ts)
                            <option value="{{ $ts->value }}">{{ $ts->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Owner picker (shows each user's calendar colour) --}}
            <div
                x-data="{ open: false }"
                class="relative"
            >
                <label class="s-field-label">Owner</label>
                @php $selectedOwner = $staff->firstWhere('id', $ownedBy); @endphp
                <button
                    type="button"
                    class="s-input flex items-center gap-2 text-left w-full"
                    x-on:click="open = !open"
                >
                    @if ($selectedOwner)
                        <x-dense::avatar
                            size="xs"
                            :initials="$selectedOwner->initials()"
                            :src="app(\App\Services\FileService::class)->signedUrlOrNull(
                                $selectedOwner->member?->icon_thumb_url,
                            )"
                            :color="str_replace('s-avatar-', '', $colorResolver->for($selectedOwner->id))"
                        />
                        <span>{{ $selectedOwner->name }}</span>
                    @else
                        <span style="color: var(--text-muted);">Unassigned</span>
                    @endif
                </button>
                <div
                    class="s-dropdown"
                    x-show="open"
                    x-cloak
                    x-on:click.outside="open = false"
                    style="max-height: 240px; overflow-y: auto; min-width: 220px;"
                >
                    @foreach ($staff as $member)
                        <div
                            class="s-dropdown-item"
                            wire:key="owner-opt-{{ $member->id }}"
                            x-on:click="open = false"
                            wire:click="$set('ownedBy', {{ $member->id }})"
                        >
                            <x-dense::avatar
                                size="xs"
                                :initials="$member->initials()"
                                :src="app(\App\Services\FileService::class)->signedUrlOrNull(
                                    $member->member?->icon_thumb_url,
                                )"
                                :color="str_replace('s-avatar-', '', $colorResolver->for($member->id))"
                            />
                            <span>{{ $member->name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Participants (other staff added to the activity) --}}
            <div
                x-data="{ open: false }"
                class="relative"
            >
                <label class="s-field-label">Participants</label>
                <button
                    type="button"
                    class="s-input flex items-center gap-2 text-left w-full"
                    x-on:click="open = !open"
                >
                    @if ($participantIds === [])
                        <span style="color: var(--text-muted);">Add participants…</span>
                    @else
                        <span>{{ count($participantIds) }} selected</span>
                    @endif
                </button>
                <div
                    class="s-dropdown"
                    x-show="open"
                    x-cloak
                    x-on:click.outside="open = false"
                    style="max-height: 240px; overflow-y: auto; min-width: 220px;"
                >
                    @foreach ($staff as $member)
                        @continue($member->member_id === null)
                        @continue($member->id === $ownedBy)
                        <label
                            class="s-dropdown-item"
                            wire:key="participant-opt-{{ $member->id }}"
                        >
                            <input
                                type="checkbox"
                                value="{{ $member->member_id }}"
                                wire:click="toggleParticipant({{ $member->member_id }})"
                                @checked(in_array($member->member_id, $participantIds, true))
                            >
                            <x-dense::avatar
                                size="xs"
                                :initials="$member->initials()"
                                :src="app(\App\Services\FileService::class)->signedUrlOrNull(
                                    $member->member?->icon_thumb_url,
                                )"
                                :color="str_replace('s-avatar-', '', $colorResolver->for($member->id))"
                            />
                            <span>{{ $member->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label
                    class="s-field-label"
                    for="cal-form-location"
                >Location</label>
                <input
                    type="text"
                    id="cal-form-location"
                    wire:model="location"
                    class="s-input"
                    placeholder="Optional"
                    autocomplete="off"
                />
            </div>

            <div>
                <label
                    class="s-field-label"
                    for="cal-form-description"
                >Description</label>
                <textarea
                    id="cal-form-description"
                    wire:model="description"
                    class="s-textarea"
                    rows="3"
                    placeholder="Optional notes"
                ></textarea>
            </div>
        </form>

        <x-slot:footer>
            <button
                type="button"
                class="s-btn s-btn-sm"
                x-on:click="$dispatch('close-modal', 'calendar-activity-form')"
            >
                Cancel
            </button>
            <button
                type="button"
                class="s-btn s-btn-sm s-btn-primary"
                wire:click="save"
            >
                {{ $activityId ? 'Save Changes' : 'Create Activity' }}
            </button>
        </x-slot:footer>
    </x-signals.modal>
</div>
