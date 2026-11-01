<section
    class="w-full flex flex-col"
    style="min-height: 0;"
>
    <x-dense::page-header title="Calendar">
        <x-slot:meta>
            @if ($loaded)
                <span>USERS: {{ $this->stats['staff'] }}</span>
                <span>ACTIVITIES: {{ $this->stats['activities'] }}</span>
                <span>COMPLETED: {{ $this->stats['completed'] }}</span>
            @else
                <span>USERS: <x-signals.skeleton
                        type="rect"
                        width="1.5rem"
                        class="!h-3 inline-block align-middle"
                    /></span>
                <span>ACTIVITIES: <x-signals.skeleton
                        type="rect"
                        width="1.5rem"
                        class="!h-3 inline-block align-middle"
                    /></span>
                <span>COMPLETED: <x-signals.skeleton
                        type="rect"
                        width="1.5rem"
                        class="!h-3 inline-block align-middle"
                    /></span>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <button
                type="button"
                class="s-btn"
                x-on:click="$dispatch('open-modal', 'calendar-feed')"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M4 11a9 9 0 0 1 9 9" />
                    <path d="M4 4a16 16 0 0 1 16 16" />
                    <circle
                        cx="5"
                        cy="19"
                        r="1"
                    />
                </svg>
                iCal Feed
            </button>
            <button
                type="button"
                class="s-btn s-btn-primary"
                x-on:click="$dispatch('open-modal', 'calendar-activity-form'); $wire.dispatch('calendar-open-form', {})"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <line
                        x1="12"
                        y1="5"
                        x2="12"
                        y2="19"
                    />
                    <line
                        x1="5"
                        y1="12"
                        x2="19"
                        y2="12"
                    />
                </svg>
                Add Activity
            </button>
        </x-slot:actions>
    </x-dense::page-header>

    <x-dense::toolbar>
        <x-signals.datepicker
            wire:key="cal-datepicker-{{ $startDate }}"
            :value="$startDate"
            wire:model.live="startDate"
            x-on:input="$wire.set('startDate', $event.detail)"
        />

        <div class="flex items-center gap-1">
            <button
                type="button"
                class="s-toolbar-btn"
                wire:click="prev"
                aria-label="Previous"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <polyline points="15 18 9 12 15 6" />
                </svg>
            </button>
            <button
                type="button"
                class="s-btn"
                wire:click="goToday"
            >Today</button>
            <button
                type="button"
                class="s-toolbar-btn"
                wire:click="next"
                aria-label="Next"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <polyline points="9 18 15 12 9 6" />
                </svg>
            </button>
        </div>

        <span class="s-toolbar-sep"></span>

        {{-- Owner filter --}}
        <div
            class="relative"
            x-data="{ open: false }"
        >
            <button
                type="button"
                class="s-btn"
                x-on:click="open = !open"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle
                        cx="9"
                        cy="7"
                        r="4"
                    />
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                Users
                @if ($ownerIds !== [])
                    <span class="s-badge">{{ count($ownerIds) }}</span>
                @endif
            </button>
            <div
                class="s-dropdown"
                x-show="open"
                x-cloak
                x-on:click.outside="open = false"
                style="max-height: 320px; overflow-y: auto; min-width: 220px;"
            >
                <div
                    class="s-dropdown-item"
                    wire:click="clearOwners"
                >
                    <span style="font-weight: {{ $ownerIds === [] ? 700 : 400 }};">All staff</span>
                </div>
                <hr class="s-dropdown-sep">
                @foreach ($this->orderedStaff() as $member)
                    <label
                        class="s-dropdown-item"
                        wire:key="owner-filter-{{ $member->id }}"
                    >
                        <input
                            type="checkbox"
                            value="{{ $member->id }}"
                            wire:click="toggleOwner({{ $member->id }})"
                            @checked($ownerIds === [] || in_array($member->id, $ownerIds, true))
                        >
                        <x-dense::avatar
                            size="xs"
                            :initials="$member->initials()"
                            :src="app(\App\Services\FileService::class)->signedUrlOrNull(
                                $member->member?->icon_thumb_url,
                            )"
                            :color="str_replace(
                                's-avatar-',
                                '',
                                app(\App\Domain\Activities\Services\Calendar\OwnerColorResolver::class)->for(
                                    $member->id,
                                ),
                            )"
                        />
                        <span>{{ $member->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <x-slot:right>
            {{-- Day / Week / Month switcher --}}
            <div class="s-viz-btns">
                <button
                    type="button"
                    class="s-viz-btn {{ $view === 'day' ? 'active' : '' }}"
                    wire:click="setView('day')"
                    title="Day"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <rect
                            x="4"
                            y="3"
                            width="16"
                            height="18"
                        />
                        <line
                            x1="4"
                            y1="8"
                            x2="20"
                            y2="8"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    class="s-viz-btn {{ $view === 'week' ? 'active' : '' }}"
                    wire:click="setView('week')"
                    title="Week"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <rect
                            x="3"
                            y="4"
                            width="18"
                            height="16"
                        />
                        <line
                            x1="9"
                            y1="4"
                            x2="9"
                            y2="20"
                        />
                        <line
                            x1="15"
                            y1="4"
                            x2="15"
                            y2="20"
                        />
                    </svg>
                </button>
                <button
                    type="button"
                    class="s-viz-btn {{ $view === 'month' ? 'active' : '' }}"
                    wire:click="setView('month')"
                    title="Month"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <rect
                            x="3"
                            y="4"
                            width="18"
                            height="18"
                        />
                        <line
                            x1="3"
                            y1="10"
                            x2="21"
                            y2="10"
                        />
                        <line
                            x1="9"
                            y1="4"
                            x2="9"
                            y2="22"
                        />
                        <line
                            x1="15"
                            y1="4"
                            x2="15"
                            y2="22"
                        />
                    </svg>
                </button>
            </div>
        </x-slot:right>
    </x-dense::toolbar>

    <div
        class="min-h-0 px-6 pb-4 max-md:px-5 max-sm:px-3 flex flex-col gap-4"
        wire:init="load"
    >
        <div
            class="flex flex-col min-h-0"
            style="height: 72vh;"
        >
            @if (!$loaded)
                {{-- First paint: skeleton only, query deferred until wire:init fires load(). --}}
                @include('activities::calendar.partials.skeleton')
            @else
                {{-- Loaded grid; hidden and replaced by the skeleton during paging round-trips. --}}
                <div
                    class="flex-1 min-h-0 flex flex-col"
                    wire:loading.remove
                    wire:target="setView,prev,next,goToday,openDayView,startDate"
                >
                    @if ($view === 'day')
                        @include('activities::calendar.partials.day-grid')
                    @elseif($view === 'month')
                        @include('activities::calendar.partials.month-grid')
                    @else
                        @include('activities::calendar.partials.week-grid')
                    @endif
                </div>
                <div
                    wire:loading.flex
                    wire:target="setView,prev,next,goToday,openDayView,startDate"
                    class="flex-1 min-h-0"
                >
                    @include('activities::calendar.partials.skeleton')
                </div>
            @endif
        </div>

        @if ($loaded)
            @include('activities::calendar.partials.unscheduled-tray')
        @endif
    </div>

    @include('activities::calendar.partials.feed-modal')

    <livewire:calendar.activity-form-modal />
    <livewire:calendar.activity-detail-modal />
</section>
