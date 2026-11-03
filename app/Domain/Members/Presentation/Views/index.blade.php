<section class="w-full">
    <x-dense::page-header title="Members">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">People &amp; Places</span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        {{-- Type filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setTypeFilter('')"
                    class="s-chip {{ $typeFilter === '' ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                All <span style="opacity: 0.6;">{{ $totalCount }}</span>
            </button>
            @foreach($membershipTypes as $type)
                <button wire:click="setTypeFilter('{{ $type->value }}')"
                        class="s-chip {{ $typeFilter === $type->value ? 'on' : '' }}">
                    @if($type === \App\Domain\Members\Enums\MembershipType::Contact)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                    @elseif($type === \App\Domain\Members\Enums\MembershipType::Organisation)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M3 21h18"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M9 16h1"/><path d="M14 8h1"/><path d="M14 12h1"/><path d="M14 16h1"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/></svg>
                    @elseif($type === \App\Domain\Members\Enums\MembershipType::Venue)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    @endif
                    {{ $type->label() }} <span style="opacity: 0.6;">{{ $typeCounts[$type->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Archive filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setArchiveFilter('active')" class="s-chip {{ $archiveFilter === 'active' ? 'on' : '' }}">Active</button>
            <button wire:click="setArchiveFilter('archived')" class="s-chip {{ $archiveFilter === 'archived' ? 'on' : '' }}">Archived</button>
            <button wire:click="setArchiveFilter('all')" class="s-chip {{ $archiveFilter === 'all' ? 'on' : '' }}">All</button>
        </div>

        {{-- Data table --}}
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Domain\Members\Models\Member::class"
            :searchable="['name']"
            :with="['emails', 'phones']"
            :with-counts="['addresses', 'emails', 'phones', 'links']"
            :scopes="$scopes"
            :refresh-events="['member-archived', 'member-restored', 'member-merged']"
            default-sort="name"
            empty-message="No members found."
            actions-view="members::partials.row-actions"
            bulk-actions-view="members::partials.bulk-actions"
            toolbar-view="members::partials.toolbar"
            entity-type="members"
            :key="'members-table-' . $typeFilter . '-' . $archiveFilter"
        />
    </div>

    <livewire:members.merge-modal />
</section>
