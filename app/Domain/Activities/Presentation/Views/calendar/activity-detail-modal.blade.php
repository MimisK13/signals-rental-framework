<div x-on:activity-detail-done.window="$dispatch('close-modal', 'calendar-activity-detail')">
    <x-signals.modal
        name="calendar-activity-detail"
        title="Activity"
        size="md"
    >
        @if ($activity)
            <div class="space-y-4">
                <div>
                    <h3
                        class="text-[var(--text-primary)]"
                        style="font-family: var(--font-display); font-size: 16px; font-weight: 700;"
                    >{{ $activity->subject }}</h3>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="s-badge s-badge-blue">{{ $activity->activity_type_name }}</span>
                        <span
                            class="s-badge {{ $activity->status_id === \App\Domain\Activities\Enums\ActivityStatus::Completed->value ? 's-badge-green' : 's-badge-amber' }}"
                        >{{ $activity->activity_status_name }}</span>
                    </div>
                </div>

                @if ($activity->owner)
                    <div class="flex items-center gap-2">
                        <x-dense::avatar
                            :initials="\Illuminate\Support\Str::of($activity->owner->name)
                                ->explode(' ')
                                ->take(2)
                                ->map(fn($w) => \Illuminate\Support\Str::substr($w, 0, 1))
                                ->implode('')"
                            :src="$ownerSrc"
                            :color="str_replace(
                                's-avatar-',
                                '',
                                app(\App\Domain\Activities\Services\Calendar\OwnerColorResolver::class)->for(
                                    $activity->owner->id,
                                ),
                            )"
                            size="sm"
                        />
                        <span class="text-sm text-[var(--text-secondary)]">{{ $activity->owner->name }}</span>
                    </div>
                @endif

                <x-signals.data-list
                    layout="vertical"
                    :items="array_filter([
                        $activity->starts_at
                            ? [
                                'label' => 'Starts At',
                                'value' => app(\App\Support\Formatter::class)->dateTime($activity->starts_at),
                            ]
                            : null,
                        $activity->ends_at
                            ? [
                                'label' => 'Ends At',
                                'value' => app(\App\Support\Formatter::class)->dateTime($activity->ends_at),
                            ]
                            : null,
                        $activity->location ? ['label' => 'Location', 'value' => $activity->location] : null,
                    ])"
                />

                @if ($activity->description)
                    <div>
                        <div class="s-field-label">Description</div>
                        <p class="text-sm text-[var(--text-secondary)] whitespace-pre-line">{{ $activity->description }}
                        </p>
                    </div>
                @endif

                @if ($activity->regarding)
                    <div>
                        <div class="s-field-label">Regarding</div>
                        @if ($regardingMember)
                            <a
                                href="{{ $regardingMember['url'] }}"
                                target="_blank"
                                rel="noopener"
                                class="group flex w-fit items-center gap-2"
                            >
                                <x-dense::avatar
                                    size="sm"
                                    :initials="$regardingMember['initials']"
                                    :src="$regardingMember['src']"
                                    color="zinc"
                                />
                                <span
                                    class="text-sm text-[var(--link)] group-hover:underline">{{ $regardingMember['name'] }}</span>
                                <svg
                                    class="h-3.5 w-3.5 text-[var(--link)]"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="M14 5h5v5" />
                                    <path d="M19 5l-7 7" />
                                    <path d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4" />
                                </svg>
                            </a>
                        @else
                            <div class="flex items-center gap-2">
                                <span
                                    class="s-badge s-badge-zinc">{{ \App\Domain\Activities\Models\Activity::regardingTypeLabel($activity->regarding_type) }}</span>
                                <span
                                    class="text-sm text-[var(--text-secondary)]">{{ $activity->regarding->name }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                @if (count($participantNames) > 0)
                    <div>
                        <div class="s-field-label">Participants</div>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            @foreach ($participantNames as $participant)
                                <div class="flex items-center gap-1.5">
                                    <x-dense::avatar
                                        size="xs"
                                        :initials="$participant['initials']"
                                        :src="$participant['src']"
                                        :color="$participant['color']"
                                    />
                                    <span
                                        class="text-sm text-[var(--text-secondary)]">{{ $participant['name'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @else
            <p class="text-sm text-[var(--text-muted)]">No activity loaded.</p>
        @endif

        <x-slot:footer>
            @if ($activity)
                <a
                    href="{{ route('activities.show', $activity->id) }}"
                    target="_blank"
                    rel="noopener"
                    class="s-btn s-btn-sm s-btn-ghost"
                >
                    Open Full Page
                </a>
                <div class="ml-auto flex items-center gap-2">
                    @if ($canComplete && !$activity->completed)
                        <button
                            type="button"
                            class="s-btn s-btn-sm s-btn-primary"
                            wire:click="complete"
                        >Complete</button>
                    @endif
                    @if ($canEdit)
                        <button
                            type="button"
                            class="s-btn s-btn-sm"
                            x-on:click="$dispatch('close-modal', 'calendar-activity-detail'); $dispatch('open-modal', 'calendar-activity-form'); $wire.dispatch('calendar-open-form', { activityId: {{ $activity->id }} })"
                        >
                            Edit
                        </button>
                    @endif
                    @if ($canDelete)
                        <button
                            type="button"
                            class="s-btn s-btn-sm s-btn-danger"
                            wire:click="delete"
                            wire:confirm="Delete this activity?"
                        >Delete</button>
                    @endif
                </div>
            @endif
        </x-slot:footer>
    </x-signals.modal>
</div>
