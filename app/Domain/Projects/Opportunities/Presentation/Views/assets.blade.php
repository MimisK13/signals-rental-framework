
<section class="w-full" data-density="compact">
    @include('project-opportunities::partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Assets', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('project-opportunities::partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'assets'])

    @php
        $statusBadge = fn (\App\Domain\Projects\Opportunities\Enums\AssetAssignmentStatus $s, ?\App\Domain\Assets\Enums\AssetLifecycleStatus $lifecycle = null) => match (true) {
            $lifecycle === \App\Domain\Assets\Enums\AssetLifecycleStatus::Lost => 's-status-red',
            $lifecycle === \App\Domain\Assets\Enums\AssetLifecycleStatus::Missing => 's-status-amber',
            default => match ($s) {
            \App\Domain\Projects\Opportunities\Enums\AssetAssignmentStatus::Allocated => 's-status-zinc',
            \App\Domain\Projects\Opportunities\Enums\AssetAssignmentStatus::Prepared => 's-status-blue',
            \App\Domain\Projects\Opportunities\Enums\AssetAssignmentStatus::Dispatched => 's-status-amber',
            \App\Domain\Projects\Opportunities\Enums\AssetAssignmentStatus::OnHire => 's-status-amber',
            \App\Domain\Projects\Opportunities\Enums\AssetAssignmentStatus::CheckedIn => 's-status-blue',
            \App\Domain\Projects\Opportunities\Enums\AssetAssignmentStatus::Finalised => 's-status-green',
            },
        };

        // The internal sub-tabs (Livewire state, NOT routes). The scan-driven four
        // carry a barcode glyph to flag the scan surface.
        $subTabs = [
            ['key' => 'functions', 'label' => __('Functions'), 'scan' => false],
            ['key' => 'allocate', 'label' => __('Allocate'), 'scan' => true],
            ['key' => 'prepare', 'label' => __('Prepare'), 'scan' => true],
            ['key' => 'book_out', 'label' => __('Book out'), 'scan' => true],
            ['key' => 'check_in', 'label' => __('Check in'), 'scan' => true],
        ];
    @endphp

    @php
        $executionStatus = $opportunity->statusEnum();
        $nextStep = match ($executionStatus) {
            \App\Domain\Projects\Opportunities\Enums\OpportunityStatus::OrderActive => __('Preparation'),
            \App\Domain\Projects\Opportunities\Enums\OpportunityStatus::OrderDispatched => __('Going out'),
            \App\Domain\Projects\Opportunities\Enums\OpportunityStatus::OrderOnHire => __('Return'),
            \App\Domain\Projects\Opportunities\Enums\OpportunityStatus::OrderReturned, \App\Domain\Projects\Opportunities\Enums\OpportunityStatus::OrderChecked => __('Checking'),
            default => $executionStatus->label(),
        };
    @endphp

    <div
        class="flex-1 space-y-5 px-6 py-4 max-md:px-5 max-sm:px-3"
        x-data="{
            subTab: @entangle('subTab'),
            focusScanner() {
                this.$nextTick(() => this.$root.querySelector('[data-scanner-input]:not([disabled])')?.focus())
            }
        }"
        x-on:warehouse-scan-ready.window="focusScanner()"
    >

        @if(session('error'))
            <x-signals.alert type="danger">{{ session('error') }}</x-signals.alert>
        @endif
        @if(session('asset_warning'))
            <x-signals.alert type="warning">{{ session('asset_warning') }}</x-signals.alert>
        @endif
        @if(session('asset_status'))
            <x-signals.alert type="success">{{ session('asset_status') }}</x-signals.alert>
        @endif

        @unless($this->isOrder())
            <x-signals.alert type="info">
                {{ __('Assets can be allocated and prepared on a reserved quotation, but booking out and checking in only become available once it is an order.') }}
            </x-signals.alert>
        @endunless

        <x-dense::card>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Execution workspace') }}</div>
                        <div class="truncate text-sm font-semibold text-[var(--text)]">{{ $opportunity->subject }}</div>
                    </div>
                    <span class="s-badge s-badge-blue">{{ $executionStatus->label() }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-xs text-[var(--text-muted)]">
                    <span><strong class="font-medium text-[var(--text)]">{{ __('Next') }}:</strong> {{ $nextStep }}</span>
                    <span><strong class="font-medium text-[var(--text)]">{{ __('Store') }}:</strong> {{ $opportunity->store?->name ?? __('No store') }}</span>
                    <span><strong class="font-medium text-[var(--text)]">{{ __('Lines') }}:</strong> {{ count($groups) }}</span>
                </div>
            </div>
        </x-dense::card>

        {{-- Internal sub-tabs (Livewire, not routes) --}}
        <div class="flex flex-wrap items-center gap-1 border-b border-[var(--border)] pb-px">
            @foreach($subTabs as $tab)
                <button type="button"
                    wire:key="subtab-{{ $tab['key'] }}"
                    wire:click="setSubTab('{{ $tab['key'] }}')"
                    @class([
                        's-tab inline-flex items-center gap-1.5',
                        'on' => $subTab === $tab['key'],
                    ])>
                    @if($tab['scan'])
                        <flux:icon.qr-code class="!size-4" />
                    @endif
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>

        {{-- ============================================================ --}}
        {{--  SUB-TAB CONTROLS                                            --}}
        {{-- ============================================================ --}}
        @if($canEdit)
            {{-- UNIFIED SCANNER WORKSPACE: an explicit mode selects the existing action. --}}
            <div class="s-panel" style="padding: 12px 14px;" x-show="subTab === 'functions'" x-cloak data-density="comfortable">
                <form wire:submit="executeScan" class="flex flex-wrap items-end gap-3" x-on:submit="$nextTick(() => focusScanner())">
                    <div class="min-w-[220px] flex-1">
                        <label for="workspace-scan-asset" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Scan equipment') }}</label>
                        <input
                            id="workspace-scan-asset"
                            data-scanner-input
                            type="text"
                            wire:model="scanAsset"
                            class="s-input w-full font-mono"
                            placeholder="{{ __('Scan / type asset number, then Enter') }}"
                            autocomplete="off"
                        >
                    </div>
                    <div class="min-w-[150px]">
                        <label for="workspace-execution-mode" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Current action') }}</label>
                        <select id="workspace-execution-mode" wire:model="executionMode" class="s-input w-full">
                            <option value="allocate">{{ __('Allocate') }}</option>
                            <option value="prepare">{{ __('Prepare') }}</option>
                            <option value="book_out" @disabled(! $this->isOrder())>{{ __('Book out') }}</option>
                            <option value="check_in" @disabled(! $this->isOrder())>{{ __('Check in') }}</option>
                        </select>
                    </div>
                    <button type="submit" class="s-btn s-btn-primary" wire:loading.attr="disabled" wire:target="executeScan">
                        <span wire:loading.remove wire:target="executeScan">{{ __('Execute') }}</span>
                        <span wire:loading wire:target="executeScan">{{ __('Working…') }}</span>
                    </button>
                </form>
                <p class="mt-2 text-[11px] text-[var(--text-muted)]">{{ __('Select the intended lifecycle action before scanning. The existing warehouse action validates the current asset state and Store.') }}</p>
            </div>

            {{-- FUNCTIONS: the multiselect-driven Action toolbar --}}
            <div class="s-panel" style="padding: 12px 14px;" x-show="subTab === 'functions'" x-cloak>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px]">
                        <label for="bulk-action" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Action') }}</label>
                        <select id="bulk-action" wire:model.live="bulkAction" class="s-input w-full">
                            <option value="">{{ __('Choose an action…') }}</option>
                            <option value="auto_allocate">{{ __('Auto-allocate') }}</option>
                            <option value="prepare">{{ __('Prepare') }}</option>
                            <option value="book_out" @disabled(! $this->isOrder())>{{ __('Book out') }}</option>
                            <option value="revert_status">{{ __('Revert status') }}</option>
                            <option value="set_container">{{ __('Set container') }}</option>
                            {{-- Phase-4 sub-hire / transfer surface — not built; honestly disabled. --}}
                            <option value="" disabled>{{ __('Set sub-rent supplier — coming in Phase 5.5') }}</option>
                            <option value="" disabled>{{ __('Transfer in — coming in Phase 5.5') }}</option>
                            <option value="" disabled>{{ __('Clear transfer — coming in Phase 5.5') }}</option>
                        </select>
                    </div>

                    @if($bulkAction === 'set_container')
                        <div class="min-w-[200px]">
                            <label for="bulk-container" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Container asset no.') }}</label>
                            <input id="bulk-container" type="text" wire:model="bulkContainer" class="s-input w-full font-mono" placeholder="{{ __('Scan / type container') }}">
                        </div>
                    @endif

                    <button type="button"
                        wire:click="runBulkAction"
                        @disabled($bulkAction === '' || count($selected) === 0)
                        class="s-btn s-btn-primary">
                        {{ __('Apply') }}
                        @if(count($selected) > 0)
                            <span class="s-badge s-badge-count">{{ count($selected) }}</span>
                        @endif
                    </button>

                    <p class="text-[11px] text-[var(--text-muted)] self-center">
                        {{ count($selected) === 0 ? __('Select rows below to enable an action.') : trans_choice(':count asset selected|:count assets selected', count($selected), ['count' => count($selected)]) }}
                    </p>
                </div>
            </div>

            {{-- ALLOCATE scan bar --}}
            <div class="s-panel" style="padding: 12px 14px;" x-show="subTab === 'allocate'" x-cloak>
                <form wire:submit="scanAllocate" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label for="alloc-asset" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Asset') }}</label>
                        <input id="alloc-asset" data-scanner-input type="text" wire:model="scanAsset" class="s-input w-full font-mono" placeholder="{{ __('Scan / type asset number, then Enter') }}" autocomplete="off">
                    </div>
                    <div class="w-24">
                        <label for="alloc-qty" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Quantity') }}</label>
                        <input id="alloc-qty" type="number" min="1" step="1" wire:model="scanQuantity" class="s-input w-full font-mono">
                    </div>
                    <div class="w-44">
                        <label for="alloc-container" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Container') }}</label>
                        <input id="alloc-container" type="text" wire:model="scanContainer" class="s-input w-full font-mono" placeholder="{{ __('Optional') }}">
                    </div>
                    <label class="flex items-center gap-2 pb-2 text-sm">
                        <input type="checkbox" wire:model="freeScan" class="s-checkbox"> {{ __('Free scan') }}
                    </label>
                    <label class="flex items-center gap-2 pb-2 text-sm">
                        <input type="checkbox" wire:model="markPrepared" class="s-checkbox"> {{ __('Mark as prepared') }}
                    </label>
                    <button type="submit" class="s-btn s-btn-outline-blue">{{ __('Allocate') }}</button>
                </form>
            </div>

            {{-- PREPARE scan bar --}}
            <div class="s-panel" style="padding: 12px 14px;" x-show="subTab === 'prepare'" x-cloak>
                <form wire:submit="scanPrepare" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label for="prep-asset" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Asset') }}</label>
                        <input id="prep-asset" data-scanner-input type="text" wire:model="scanAsset" class="s-input w-full font-mono" placeholder="{{ __('Scan / type asset number, then Enter') }}" autocomplete="off">
                    </div>
                    <div class="w-44">
                        <label for="prep-container" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Container') }}</label>
                        <input id="prep-container" type="text" wire:model="scanContainer" class="s-input w-full font-mono" placeholder="{{ __('Optional') }}">
                    </div>
                    <button type="submit" class="s-btn s-btn-outline-blue">{{ __('Prepare') }}</button>
                </form>
            </div>

            {{-- BOOK OUT scan bar --}}
            <div class="s-panel" style="padding: 12px 14px;" x-show="subTab === 'book_out'" x-cloak>
                <form wire:submit="scanBookOut" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label for="out-asset" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Asset') }}</label>
                        <input id="out-asset" data-scanner-input type="text" wire:model="scanAsset" class="s-input w-full font-mono" placeholder="{{ __('Scan / type asset number, then Enter') }}" autocomplete="off" @disabled(! $this->isOrder())>
                    </div>
                    <div class="w-44">
                        <label for="out-container" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Container') }}</label>
                        <input id="out-container" type="text" wire:model="scanContainer" class="s-input w-full font-mono" placeholder="{{ __('Optional') }}" @disabled(! $this->isOrder())>
                    </div>
                    <button type="submit" class="s-btn s-btn-outline-green" @disabled(! $this->isOrder())>{{ __('Book out') }}</button>
                </form>
            </div>

            {{-- CHECK IN scan bar --}}
            <div class="s-panel" style="padding: 12px 14px;" x-show="subTab === 'check_in'" x-cloak>
                <form wire:submit="scanCheckIn" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label for="in-asset" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Asset') }}</label>
                        <input id="in-asset" data-scanner-input type="text" wire:model="scanAsset" class="s-input w-full font-mono" placeholder="{{ __('Scan / type asset number, then Enter') }}" autocomplete="off" @disabled(! $this->isOrder())>
                    </div>
                    <div class="w-44">
                        <label for="in-container" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Container') }}</label>
                        <input id="in-container" type="text" wire:model="scanContainer" class="s-input w-full font-mono" placeholder="{{ __('Optional') }}" @disabled(! $this->isOrder())>
                    </div>
                    <button type="submit" class="s-btn s-btn-outline-green" @disabled(! $this->isOrder())>{{ __('Check in') }}</button>
                </form>
            </div>
        @endif

        @if($containers !== [])
            <x-dense::card
                title="{{ __('Containers') }}"
                data-density="compact"
            >
                <div class="flex flex-wrap items-center gap-3 border-b border-[var(--card-border)] px-3 py-2 text-xs text-[var(--text-muted)]">
                    <span class="font-mono">
                        {{ $containerProgress['dispatched'] }} / {{ $containerProgress['total'] }} {{ __('booked out') }}
                    </span>
                    @if($subTab === 'check_in')
                        <span class="font-mono">{{ $containerProgress['to_return'] }} {{ __('to check in') }}</span>
                    @elseif($containerProgress['ready'] > 0)
                        <span class="font-mono">{{ $containerProgress['ready'] }} {{ __('ready') }}</span>
                    @endif
                </div>
                <x-signals.table-wrap>
                    <table class="s-table s-table-compact w-full">
                        <thead>
                            <tr>
                                <th>{{ __('Container') }}</th>
                                <th>{{ __('Store') }}</th>
                                <th>{{ __('Contents') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-center"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($containers as $container)
                                <tr wire:key="execution-container-{{ $container['uuid'] }}">
                                    <td>
                                        <a href="{{ $container['show_url'] }}" wire:navigate.hover class="font-medium text-[var(--text)] hover:text-[var(--blue)]">
                                            {{ $container['name'] }}
                                        </a>
                                        @if($container['barcode'])
                                            <div class="font-mono text-xs text-[var(--text-muted)]">{{ $container['barcode'] }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $container['store'] }}</td>
                                    <td class="tabular-nums">
                                        {{ $container['contents_count'] }}
                                        @if($container['children_count'] > 0)
                                            <span class="text-xs text-[var(--text-muted)]">· {{ $container['children_count'] }} {{ __('nested') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="s-badge s-badge-zinc">{{ $container['status'] }}</span>
                                        <span class="text-xs text-[var(--text-muted)]">{{ $container['mode'] }}</span>
                                        @if($container['compliance_attention'])
                                            <span class="mt-1 block s-badge s-badge-amber">{{ $container['compliance_label'] }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="flex flex-wrap justify-center gap-1">
                                            <a href="{{ $container['show_url'] }}" wire:navigate.hover class="s-btn-ghost s-btn-xs">
                                                {{ __('Open') }}
                                            </a>
                                            @if($container['status_value'] === 'open' && $container['can_pack'])
                                                <a href="{{ $container['pack_url'] }}" wire:navigate.hover class="s-btn-ghost s-btn-xs">
                                                    {{ __('Pack') }}
                                                </a>
                                            @endif
                                            @if($container['status_value'] === 'sealed' && $container['can_dispatch'] && $this->isOrder())
                                                <button
                                                    type="button"
                                                    wire:click="dispatchContainerFromWorkspace('{{ $container['uuid'] }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="dispatchContainerFromWorkspace('{{ $container['uuid'] }}')"
                                                    class="s-btn-ghost s-btn-xs"
                                                >
                                                    {{ __('Book out') }}
                                                </button>
                                            @endif
                                            @if($container['status_value'] === 'dispatched' && $container['is_bookable_kit'] && $container['can_return'] && $this->isOrder())
                                                <button
                                                    type="button"
                                                    wire:click="returnContainerFromWorkspace('{{ $container['uuid'] }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="returnContainerFromWorkspace('{{ $container['uuid'] }}')"
                                                    class="s-btn-ghost s-btn-xs"
                                                >
                                                    {{ __('Check in') }}
                                                </button>
                                            @endif
                                            @if($container['status_value'] === 'returned' && $container['can_verify'])
                                                <button
                                                    type="button"
                                                    wire:click="verifyContainerFromWorkspace('{{ $container['uuid'] }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="verifyContainerFromWorkspace('{{ $container['uuid'] }}')"
                                                    class="s-btn-ghost s-btn-xs"
                                                >
                                                    {{ __('Verify') }}
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-signals.table-wrap>
            </x-dense::card>
        @endif

        {{-- ============================================================ --}}
        {{--  SHARED GROUPED ASSET TABLE (all sub-tabs)                    --}}
        {{-- ============================================================ --}}
        @if(empty($groups))
            <x-dense::empty-state
                title="{{ __('No line items') }}"
                description="{{ __('Add line items on the Overview tab before allocating and dispatching assets.') }}">
                <x-slot:icon><flux:icon.cube class="!size-7" /></x-slot:icon>
            </x-dense::empty-state>
        @else
            <x-signals.table-wrap>
                <table class="s-table" x-data="assetTable()">
                    <thead>
                        <tr>
                            <th style="width: 32px;">
                                <input type="checkbox" class="s-checkbox"
                                    @if(! $canEdit) disabled @endif
                                    wire:change="toggleSelectAll($event.target.checked)"
                                    @checked(count($selected) > 0 && count($selected) === collect($groups)->sum(fn ($g) => count($g['rows'])))>
                            </th>
                            <th>{{ __('Product') }}</th>
                            <th>{{ __('Asset Number') }}</th>
                            <th style="width: 90px;">{{ __('Type') }}</th>
                            <th style="width: 130px;">{{ __('Status') }}</th>
                            <th style="width: 130px;">{{ __('Container') }}</th>
                            <th class="text-center" style="width: 150px;">{{ __('Progress') }}</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>

                    @foreach($groups as $group)
                        @php $groupKey = 'asset-grp-'.$group['id']; @endphp
                        {{-- Group header row (product) + per-group expand/lock toggle --}}
                        <tbody wire:key="grp-{{ $group['id'] }}">
                            <tr class="s-table-group-row">
                                <td></td>
                                <td colspan="5">
                                    <button type="button" class="inline-flex items-center gap-2 font-semibold"
                                        x-on:click="toggleGroup('{{ $groupKey }}')">
                                        <span class="text-xs text-[var(--text-faint)]" x-text="isCollapsed('{{ $groupKey }}') ? '▸' : '▾'"></span>
                                        <span>{{ $group['name'] }}</span>
                                        @if(! $group['serialised'])
                                            <span class="s-badge s-badge-zinc s-badge-outline">{{ __('Bulk') }}</span>
                                        @endif
                                        <span class="text-xs text-[var(--text-faint)]">{{ count($group['rows']) }} {{ \Illuminate\Support\Str::plural('asset', count($group['rows'])) }}</span>
                                        @if($group['serialised'] && ($group['inspection_finalised'] > 0 || $group['inspection_pending'] > 0))
                                            <span class="text-xs text-[var(--text-muted)]">
                                                {{ __('Inspection') }}: {{ $group['inspection_finalised'] }}/{{ $group['inspection_finalised'] + $group['inspection_pending'] }}
                                                @if($group['inspection_pending'] > 0)
                                                    · {{ $group['inspection_pending'] }} {{ __('pending') }}
                                                @endif
                                            </span>
                                        @endif
                                        @if($group['serialised'] && $group['still_out'] > 0)
                                            <span class="s-badge s-badge-zinc">{{ $group['still_out'] }} {{ __('still out') }}</span>
                                        @endif
                                        @if($group['serialised'] && $group['missing'] > 0)
                                            <span class="s-badge s-badge-amber">{{ $group['missing'] }} {{ __('missing') }}</span>
                                        @endif
                                        @if($group['serialised'] && $group['lost'] > 0)
                                            <span class="s-badge s-badge-red">{{ $group['lost'] }} {{ __('lost') }}</span>
                                        @endif
                                    </button>
                                </td>
                                <td class="text-center font-mono text-xs text-[var(--text-muted)]">
                                    <div>{{ $group['progress_done'] }} / {{ $group['progress_required'] }} {{ $group['progress_label'] }}</div>
                                    @if($group['remaining_for_action'] > 0)
                                        <div class="mt-1 text-[var(--text-faint)]">{{ $group['remaining_label'] }}: {{ $this->formatQuantity($group['remaining_for_action']) }}</div>
                                    @else
                                        <div class="mt-1 text-[var(--green)]">{{ __('Complete') }}</div>
                                    @endif
                                </td>
                                <td></td>
                            </tr>
                            @if(! $group['serialised'])
                                <tr class="s-table-line" wire:key="bulk-controls-{{ $group['id'] }}">
                                    <td></td>
                                    <td colspan="5">
                                        <div class="flex flex-wrap items-center gap-3" data-density="comfortable">
                                            <span class="text-xs text-[var(--text-muted)]">{{ __('Bulk execution') }}</span>
                                            <span class="text-xs font-mono text-[var(--text-muted)]">
                                                {{ __('To dispatch') }}: {{ $group['remaining_dispatch'] }} · {{ __('Outstanding return') }}: {{ $group['remaining_return'] }}
                                            </span>
                                            @if($canEdit && $this->isOrder())
                                                <div class="ml-auto flex flex-wrap items-center gap-2">
                                                    <input
                                                        type="number"
                                                        min="0.01"
                                                        step="any"
                                                        wire:model="bulkQuantities.{{ $group['id'] }}"
                                                        class="s-input w-24 font-mono"
                                                        placeholder="{{ $this->formatQuantity($group['remaining_for_action']) }}"
                                                        aria-label="{{ __('Bulk quantity') }}"
                                                        @disabled($group['remaining_for_action'] <= 0)
                                                    >
                                                    @if($subTab === 'functions' || $subTab === 'book_out')
                                                        <button
                                                            type="button"
                                                            wire:click="dispatchBulk({{ $group['id'] }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="dispatchBulk({{ $group['id'] }})"
                                                            class="s-btn s-btn-outline-green"
                                                            @disabled((float) $group['remaining_dispatch'] <= 0)
                                                        >
                                                            {{ __('Dispatch') }}
                                                        </button>
                                                    @endif
                                                    @if($subTab === 'functions' || $subTab === 'check_in')
                                                        <button
                                                            type="button"
                                                            wire:click="returnBulk({{ $group['id'] }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="returnBulk({{ $group['id'] }})"
                                                            class="s-btn s-btn-outline-blue"
                                                            @disabled((float) $group['remaining_return'] <= 0)
                                                        >
                                                            {{ __('Return') }}
                                                        </button>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="ml-auto text-xs text-[var(--text-faint)]">{{ __('Bulk actions are available on an active order.') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            @endif
                        </tbody>

                        <tbody x-show="!isCollapsed('{{ $groupKey }}')">
                            @forelse($group['rows'] as $row)
                                <tr class="s-table-line" wire:key="asset-{{ $row['id'] }}">
                                    <td class="text-center">
                                        <input type="checkbox" class="s-checkbox" value="{{ $row['id'] }}"
                                            wire:model.live="selected"
                                            @if(! $canEdit) disabled @endif>
                                    </td>
                                    <td class="text-[var(--text-muted)]">{{ $group['name'] }}</td>
                                    <td class="font-medium font-mono">{{ $row['asset_number'] }}</td>
                                    <td>{{ $group['type'] }}</td>
                                    <td>
                                        <span class="s-status {{ $statusBadge($row['status'], $row['lifecycle_status']) }}"><span class="s-status-dot"></span> {{ $row['status_label'] }}</span>
                                    </td>
                                    <td class="text-[var(--text-muted)]">
                                        @if($row['container'])
                                            <span class="s-chip">{{ $row['container'] }}</span>
                                        @else
                                            <span class="text-[var(--text-faint)]">—</span>
                                        @endif
                                    </td>
                                    <td></td>
                                    <td class="text-center">
                                        @if($canEdit && $this->assetActions($row['status'], $row['lifecycle_status'], $row['store_id']) !== [])
                                            <div class="relative inline-block" x-data="rowActionsMenu()">
                                                <button type="button" class="s-btn-icon" x-ref="trigger" x-on:click="toggle()">▾</button>
                                                {{-- Teleported to <body> so the table-wrap overflow can't clip it. --}}
                                                <template x-teleport="body">
                                                    <div class="s-dropdown" x-show="open" x-cloak
                                                        x-on:click.outside="open = false"
                                                        x-on:keydown.escape.window="open = false"
                                                        :style="menuStyle"
                                                        style="position: fixed; z-index: 1000; min-width: 180px;">
                                                        {{-- The menu is teleported to <body>, OUTSIDE the Livewire
                                                             component root, so bare `wire:click` loses its
                                                             component binding (Livewire 4) and become no-ops. Calls are
                                                             made through `$wire` (captured from the original Alpine scope,
                                                             preserved across the teleport) so the actions actually fire;
                                                             confirmations use a native confirm() guard in the handler. --}}
                                                        @foreach($this->assetActions($row['status'], $row['lifecycle_status'], $row['store_id']) as $act)
                                                            <button type="button"
                                                                wire:key="act-{{ $row['id'] }}-{{ $act['action'] }}"
                                                                class="s-dropdown-item w-full text-left"
                                                                @if($act['action'] === 'substitute')
                                                                    x-on:click="open = false; $wire.substitute({{ $row['id'] }}); $dispatch('open-modal', 'substitute-asset')"
                                                                @elseif($act['confirm'])
                                                                    x-on:click="open = false; if (window.confirm(@js($act['confirm']))) { $wire.{{ $act['action'] }}({{ $row['id'] }}); }"
                                                                @else
                                                                    x-on:click="open = false; $wire.{{ $act['action'] }}({{ $row['id'] }})"
                                                                @endif>
                                                                {{ $act['label'] }}
                                                            </button>
                                                        @endforeach
                                                        @if($row['container'])
                                                            <hr class="s-dropdown-sep">
                                                            <button type="button" class="s-dropdown-item w-full text-left"
                                                                x-on:click="open = false; $wire.clearContainer({{ $row['id'] }})">
                                                                {{ __('Clear container') }}
                                                            </button>
                                                        @endif
                                                    </div>
                                                </template>
                                            </div>
                                        @else
                                            <span class="text-[var(--text-faint)]">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr wire:key="grp-empty-{{ $group['id'] }}">
                                    <td></td>
                                    <td colspan="7" class="text-sm text-[var(--text-muted)]">
                                        {{ $group['serialised']
                                            ? __('No assets allocated to this line yet — use the Allocate tab.')
                                            : __('Bulk line — dispatched by quantity (see the tally above).') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    @endforeach
                </table>
            </x-signals.table-wrap>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{--  RETURN INSPECTION MODAL                                     --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="inspect-asset" title="{{ __('Return inspection') }}" :teleport="false">
        <div class="space-y-4">
            <div class="rounded-md border border-[var(--border)] bg-[var(--surface-muted)] p-3 text-sm">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Asset') }}</div>
                <div class="mt-1 font-mono font-medium text-[var(--text)]">{{ $inspectionAssetLabel ?? '—' }}</div>
                <div class="mt-1 text-xs text-[var(--text-muted)]">{{ __('Checked in — awaiting inspection') }}</div>
            </div>

            <div>
                <label for="inspection-condition" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Condition') }}</label>
                <select id="inspection-condition" wire:model="inspectionCondition" class="s-input w-full">
                    <option value="{{ AssetCondition::Good->value }}">{{ AssetCondition::Good->label() }}</option>
                    <option value="{{ AssetCondition::Damaged->value }}">{{ AssetCondition::Damaged->label() }}</option>
                </select>
                @error('inspectionCondition') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="inspection-notes" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Notes') }}</label>
                <textarea id="inspection-notes" wire:model="inspectionNotes" rows="3" class="s-input w-full"></textarea>
                @error('inspectionNotes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            @if($inspectionCondition === AssetCondition::Damaged->value)
                @if($this->canCreateRepair())
                    <label class="flex items-start gap-2 text-sm text-[var(--text)]">
                        <input type="checkbox" wire:model.live="sendToRepair" class="s-checkbox mt-0.5">
                        <span>
                            <span class="font-medium">{{ __('Send to repair') }}</span>
                            <span class="block text-xs text-[var(--text-muted)]">{{ __('Create a repair record for this physical asset.') }}</span>
                        </span>
                    </label>
                    @error('sendToRepair') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                    @if($sendToRepair)
                        <label class="flex items-start gap-2 text-sm text-[var(--text)]">
                            <input type="checkbox" wire:model.live="repairUnusable" class="s-checkbox mt-0.5">
                            <span>
                                <span class="font-medium">{{ __('Equipment is unusable') }}</span>
                                <span class="block text-xs text-[var(--text-muted)]">{{ __('Keep this asset unavailable until the repair domain releases it.') }}</span>
                            </span>
                        </label>
                        @error('repairUnusable') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @endif
                @else
                    <p class="text-xs text-[var(--text-muted)]">{{ __('You can record the damage, but you do not have permission to create a repair.') }}</p>
                @endif
            @endif
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'inspect-asset')" class="s-btn s-btn-ghost">{{ __('Cancel') }}</button>
            <button type="button" wire:click="finaliseInspection" wire:loading.attr="disabled" class="s-btn s-btn-primary">
                {{ $inspectionCondition === AssetCondition::Damaged->value && $sendToRepair ? __('Finalise & send to repair') : __('Finalise inspection') }}
            </button>
        </x-slot:footer>
    </x-signals.modal>

    {{-- ============================================================ --}}
    {{--  SUBSTITUTE-ASSET MODAL                                       --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="substitute-asset" title="{{ __('Substitute asset') }}"
        x-on:asset-modal-close.window="if ($event.detail?.name === 'substitute-asset') open = false">
        <div class="space-y-3">
            <div>
                <label for="substitute-stock-level" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Replacement asset') }}</label>
                @if($freeAssets->isEmpty())
                    <p class="text-sm text-[var(--text-muted)]">{{ __('No free serialised assets are available to substitute in.') }}</p>
                @else
                    <select id="substitute-stock-level" wire:model="substituteStockLevelId" class="s-input w-full">
                        <option value="">{{ __('Select an asset…') }}</option>
                        @foreach($freeAssets as $free)
                            <option value="{{ $free['id'] }}" wire:key="sub-free-{{ $free['id'] }}">{{ $free['label'] }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div>
                <label for="substitute-reason" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Reason (optional)') }}</label>
                <textarea id="substitute-reason" wire:model="substituteReason" rows="2" class="s-input w-full"></textarea>
            </div>
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'substitute-asset')" class="s-btn s-btn-ghost">{{ __('Cancel') }}</button>
            <button type="button" wire:click="confirmSubstitute" @disabled($freeAssets->isEmpty()) class="s-btn s-btn-primary">{{ __('Substitute') }}</button>
        </x-slot:footer>
    </x-signals.modal>

    <x-signals.modal name="container-dispatch-amendment" title="{{ __('Container dispatch amendments') }}" size="lg" :teleport="false">
        @if($containerDispatchPrompt)
            <p class="text-sm text-[var(--muted)] mb-4">{{ $containerDispatchPrompt['message'] ?? '' }}</p>
            <div class="space-y-2 mb-4 max-h-64 overflow-y-auto">
                @foreach($containerDispatchPrompt['amendments'] ?? [] as $amendment)
                    <div class="text-sm border border-[var(--border)] rounded-md p-3" wire:key="asset-amendment-{{ $amendment['product_id'] }}">
                        <div class="font-medium">{{ $amendment['product_name'] ?? 'Product #'.($amendment['product_id'] ?? '') }}</div>
                        <div class="text-[var(--muted)]">
                            {{ str($amendment['reason'] ?? '')->headline() }} —
                            {{ $amendment['required_action'] ?? 'add' }} × {{ $amendment['suggested_quantity'] ?? '1' }}
                        </div>
                    </div>
                @endforeach
                @foreach($containerDispatchPrompt['validation'] ?? [] as $row)
                    <div class="text-xs font-mono text-[var(--muted)]" wire:key="asset-validation-{{ $row['product_id'] ?? $loop->index }}">
                        {{ $row['product_name'] ?? '' }}: {{ $row['verdict'] ?? '' }}
                    </div>
                @endforeach
            </div>
            <x-slot:footer>
                <button type="button" class="s-btn s-btn-ghost" wire:click="dismissContainerDispatchPrompt">{{ __('Cancel') }}</button>
                @if(auth()->user()?->can('opportunities.dispatch_add_items'))
                    <button type="button" class="s-btn s-btn-primary"
                        wire:click="confirmContainerDispatchAmendments({{ json_encode(array_map(
                            static fn (array $row): array => [
                                'product_id' => $row['product_id'],
                                'action' => $row['required_action'] ?? 'add',
                                'quantity' => $row['suggested_quantity'] ?? '1',
                                'price' => $row['suggested_price'] ?? null,
                            ],
                            $containerDispatchPrompt['amendments'] ?? [],
                        )) }})">
                        {{ __('Apply amendments and dispatch') }}
                    </button>
                @endif
            </x-slot:footer>
        @endif
    </x-signals.modal>

    @include('project-opportunities::partials.opportunity-action-modals')
</section>

@script
<script>
    // Per-group collapse state for the shared asset table (client-only; mirrors the
    // line-item editor's grouping convention).
    Alpine.data('assetTable', () => ({
        collapsedGroups: {},
        isCollapsed(key) { return !!this.collapsedGroups[key]; },
        toggleGroup(key) { this.collapsedGroups[key] = !this.collapsedGroups[key]; },
    }));

    // Row-actions ("▾") dropdown, teleported to <body> so the table-wrap overflow
    // can never clip it; positioned fixed against the trigger's viewport rect.
    Alpine.data('rowActionsMenu', () => ({
        open: false,
        menuStyle: '',
        toggle() { this.open ? this.close() : this.openMenu(); },
        openMenu() {
            const rect = this.$refs.trigger.getBoundingClientRect();
            const right = Math.max(8, window.innerWidth - rect.right);
            this.menuStyle = `top: ${rect.bottom + 4}px; right: ${right}px;`;
            this.open = true;
        },
        close() { this.open = false; },
        init() {
            this._onViewportChange = () => { if (this.open) this.close(); };
            window.addEventListener('scroll', this._onViewportChange, true);
            window.addEventListener('resize', this._onViewportChange);
        },
        destroy() {
            window.removeEventListener('scroll', this._onViewportChange, true);
            window.removeEventListener('resize', this._onViewportChange);
        },
    }));
</script>
@endscript
