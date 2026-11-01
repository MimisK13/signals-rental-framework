<section class="w-full">
    <x-dense::page-header title="Activities" />

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        {{-- Type filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button
                wire:click="setTypeFilter('')"
                class="s-chip {{ $typeFilter === '' ? 'on' : '' }}"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="w-3 h-3"
                >
                    <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                All <span style="opacity: 0.6;">{{ $totalCount }}</span>
            </button>
            @foreach ($activityTypes as $type)
                <button
                    wire:click="setTypeFilter('{{ $type->id }}')"
                    class="s-chip {{ $typeFilter === (string) $type->id ? 'on' : '' }}"
                >
                    {{ $type->name }} <span style="opacity: 0.6;">{{ $typeCounts[$type->id] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Status filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button
                wire:click="setStatusFilter('')"
                class="s-chip {{ $statusFilter === '' ? 'on' : '' }}"
            >All Statuses</button>
            @foreach ($activityStatuses as $status)
                <button
                    wire:click="setStatusFilter('{{ $status->value }}')"
                    class="s-chip {{ $statusFilter === (string) $status->value ? 'on' : '' }}"
                >
                    {{ $status->label() }} <span style="opacity: 0.6;">{{ $statusCounts[$status->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Data table --}}
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Domain\Activities\Models\Activity::class"
            :searchable="['subject']"
            :with="['owner', 'regarding', 'type']"
            :scopes="$scopes"
            :refresh-events="['activity-deleted', 'activity-completed']"
            default-sort="-created_at"
            empty-message="No activities found."
            actions-view="activities::partials.row-actions"
            bulk-actions-view="activities::partials.bulk-actions"
            toolbar-view="activities::partials.toolbar"
            entity-type="activities"
            :key="'activities-table-' . $typeFilter . '-' . $statusFilter"
        />
    </div>
</section>
