
<section class="w-full">
    <x-dense::page-header title="Opportunities">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Job Planning</span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        {{-- State filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setStateFilter('')"
                    class="s-chip {{ $stateFilter === '' ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                All <span class="s-chip-count">{{ $totalCount }}</span>
            </button>
            @foreach($opportunityStates as $state)
                <button wire:click="setStateFilter('{{ $state->value }}')"
                        class="s-chip {{ $stateFilter === (string) $state->value ? 'on' : '' }}">
                    {{ $state->label() }} <span class="s-chip-count">{{ $stateCounts[$state->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Archive + shortage filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setArchiveFilter('active')" class="s-chip {{ $archiveFilter === 'active' ? 'on' : '' }}">Active</button>
            <button wire:click="setArchiveFilter('archived')" class="s-chip {{ $archiveFilter === 'archived' ? 'on' : '' }}">Archived</button>
            <button wire:click="setArchiveFilter('all')" class="s-chip {{ $archiveFilter === 'all' ? 'on' : '' }}">All</button>

            <span class="mx-1 text-[var(--text-faint)]">·</span>

            <button wire:click="toggleShortageFilter"
                    class="s-chip {{ $shortageFilter ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                With shortages <span class="s-chip-count">{{ $shortageCount }}</span>
            </button>
        </div>

        {{-- Data table --}}
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Domain\Projects\Opportunities\Models\Opportunity::class"
            :searchable="['subject', 'number', 'reference']"
            :with="['member', 'store']"
            :scopes="$scopes"
            :refresh-events="['opportunity-archived', 'opportunity-restored']"
            default-sort="-created_at"
            empty-message="No opportunities found."
            actions-view="project-opportunities::partials.row-actions"
            bulk-actions-view="project-opportunities::partials.bulk-actions"
            toolbar-view="project-opportunities::partials.toolbar"
            entity-type="opportunities"
            :key="'opportunities-table-' . $stateFilter . '-' . $archiveFilter . '-' . ($shortageFilter ? '1' : '0')"
        />
    </div>
</section>
