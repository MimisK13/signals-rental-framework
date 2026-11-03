
<section class="w-full">
    @include('project-opportunities::partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Versions & Timeline', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('project-opportunities::partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'versions'])

    @php
        $statusBadge = fn (\App\Domain\Projects\Opportunities\Enums\VersionStatus $status): string => match ($status) {
            \App\Domain\Projects\Opportunities\Enums\VersionStatus::Accepted => 's-badge-green',
            \App\Domain\Projects\Opportunities\Enums\VersionStatus::Declined => 's-badge-red',
            \App\Domain\Projects\Opportunities\Enums\VersionStatus::Superseded => 's-badge-zinc',
            \App\Domain\Projects\Opportunities\Enums\VersionStatus::Sent => 's-badge-blue',
            \App\Domain\Projects\Opportunities\Enums\VersionStatus::Draft => 's-badge-amber',
        };
    @endphp

    <div class="flex-1 space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">

        @if(session('error'))
            <x-signals.alert type="danger">{{ session('error') }}</x-signals.alert>
        @endif
        @if(session('version_status'))
            <x-signals.alert type="success">{{ session('version_status') }}</x-signals.alert>
        @endif

        @unless($this->isQuotation())
            <x-signals.alert type="info">
                {{ __('Versions can only be edited while the opportunity is a Quotation. They are read-only in the current state.') }}
            </x-signals.alert>
        @endunless

        {{-- ============================================================ --}}
        {{--  TWO-COLUMN LAYOUT — versions LEFT, timeline RIGHT.           --}}
        {{--  Collapses to a single stacked column on small screens.      --}}
        {{-- ============================================================ --}}
        <div class="grid grid-cols-[1fr_minmax(320px,28rem)] items-start gap-6 max-lg:grid-cols-1">

        {{-- ---------- LEFT: version tree + diff ---------- --}}
        <div class="min-w-0 space-y-6">

        {{-- ============================================================ --}}
        {{--  VERSION TREE (revisions + alternatives, active marked)       --}}
        {{-- ============================================================ --}}
        <x-signals.panel title="Quote Versions">
            @if($canEdit && $this->isQuotation())
                <x-slot:headerActions>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                wire:click="openCreate({{ \App\Domain\Projects\Opportunities\Enums\VersionType::Revision->value }})"
                                x-on:click="$dispatch('open-modal', 'create-version')"
                                class="s-btn s-btn-sm s-btn-outline-blue">
                            <flux:icon.plus class="!size-3.5" /> {{ __('New revision') }}
                        </button>
                        <button type="button"
                                wire:click="openCreate({{ \App\Domain\Projects\Opportunities\Enums\VersionType::Alternative->value }})"
                                x-on:click="$dispatch('open-modal', 'create-version')"
                                class="s-btn s-btn-sm s-btn-outline-blue">
                            <flux:icon.plus class="!size-3.5" /> {{ __('New alternative') }}
                        </button>
                    </div>
                </x-slot:headerActions>
            @endif

            <div>
                @if($versions->isEmpty())
                    <x-dense::empty-state
                        title="{{ __('No versions yet') }}"
                        description="{{ __('This opportunity has not been split into quote versions. Create a revision or alternative to start tracking versions.') }}">
                        <x-slot:icon><flux:icon.document-duplicate class="!size-7" /></x-slot:icon>
                    </x-dense::empty-state>
                @else
                    <x-signals.version-tree>
                        @foreach($versions as $version)
                            @php
                                $data = \App\Domain\Projects\Opportunities\Data\OpportunityVersionData::fromModel($version);
                            @endphp
                            <div wire:key="version-{{ $version->id }}">
                                <div class="s-vt-node {{ $version->is_active ? 's-vt-node-active' : '' }}">
                                    <div class="s-vt-node-header">
                                        <span class="s-vt-version-label">V{{ $version->version_number }}</span>
                                        @if($version->label)
                                            <span class="s-vt-version-name">{{ $version->label }}</span>
                                        @endif
                                        <span class="s-badge s-badge-zinc s-badge-outline">{{ $version->version_type->label() }}</span>
                                        <span class="s-badge {{ $statusBadge($version->status) }}">{{ $version->status->label() }}</span>
                                        @if($version->is_active)
                                            <span class="s-vt-confirmed-marker">{{ __('ACTIVE') }}</span>
                                        @endif
                                        <span class="s-vt-version-total">{{ $data->charge_total }}</span>
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span class="s-vt-version-date">
                                            {{ optional($version->created_at)->format('d M Y H:i') }}
                                        </span>
                                        @if($version->author)
                                            <span class="text-[12px] text-[var(--text-muted)]">{{ __('by') }} {{ $version->author->name }}</span>
                                        @endif
                                        @if($version->parent_version_id)
                                            <span class="text-[12px] text-[var(--text-muted)]">{{ __('revises') }} #{{ $version->parent_version_id }}</span>
                                        @endif
                                        @if($version->status === \App\Domain\Projects\Opportunities\Enums\VersionStatus::Declined && $version->decline_reason)
                                            <span class="text-[12px] text-[var(--red)]">{{ __('Declined') }}: {{ $version->decline_reason }}</span>
                                        @endif
                                    </div>

                                    @if($canEdit)
                                        <div class="mt-3 flex flex-wrap items-center gap-1 border-t border-[var(--border)] pt-2">
                                            @if($this->canActivate($version))
                                                <button type="button" wire:click="activate({{ $version->id }})"
                                                        wire:confirm="{{ __('Make this the active version? Reserved demand and the opportunity totals will swap to it.') }}"
                                                        class="s-btn s-btn-xs s-btn-outline-green">{{ __('Make active') }}</button>
                                            @endif
                                            @if($this->canSend($version))
                                                <button type="button" wire:click="send({{ $version->id }})"
                                                        class="s-btn s-btn-xs s-btn-outline-blue">{{ __('Send') }}</button>
                                            @endif
                                            @if($this->canAcceptOrDecline($version))
                                                <button type="button" wire:click="accept({{ $version->id }})"
                                                        class="s-btn s-btn-xs s-btn-outline-green">{{ __('Accept') }}</button>
                                                <button type="button" wire:click="promptDecline({{ $version->id }})"
                                                        x-on:click="$dispatch('open-modal', 'decline-version')"
                                                        class="s-btn s-btn-xs s-btn-ghost">{{ __('Decline') }}</button>
                                            @endif
                                            @if($this->canRename($version))
                                                <button type="button" wire:click="promptRename({{ $version->id }})"
                                                        x-on:click="$dispatch('open-modal', 'rename-version')"
                                                        class="s-btn s-btn-xs s-btn-ghost">{{ __('Rename') }}</button>
                                            @endif
                                            @if($this->canDelete($version, $versionCount))
                                                <button type="button" wire:click="deleteVersion({{ $version->id }})"
                                                        wire:confirm="{{ __('Delete this version? Its line items and demands are removed.') }}"
                                                        class="s-btn s-btn-xs s-btn-ghost text-[var(--red)]">{{ __('Delete') }}</button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </x-signals.version-tree>
                @endif
            </div>
        </x-signals.panel>

        {{-- ============================================================ --}}
        {{--  VERSION DIFF (select two versions → field/item delta)        --}}
        {{-- ============================================================ --}}
        @if($versions->count() >= 2)
            <x-signals.panel title="Compare Versions">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="diff-from" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('From') }}</label>
                        <select id="diff-from" wire:model.live="diffFromId" class="s-input">
                            <option value="">{{ __('Select a version') }}</option>
                            @foreach($versions as $version)
                                <option value="{{ $version->id }}" wire:key="from-{{ $version->id }}">
                                    V{{ $version->version_number }}{{ $version->label ? ' — '.$version->label : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="diff-to" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('To') }}</label>
                        <select id="diff-to" wire:model.live="diffToId" class="s-input">
                            <option value="">{{ __('Select a version') }}</option>
                            @foreach($versions as $version)
                                <option value="{{ $version->id }}" wire:key="to-{{ $version->id }}">
                                    V{{ $version->version_number }}{{ $version->label ? ' — '.$version->label : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($diff !== null)
                    <div class="mt-4 space-y-4">
                        <div class="flex flex-wrap items-center gap-4 text-sm">
                            <span class="text-[var(--text-muted)]">{{ __('V') }}{{ $diff->source_version_number }} {{ $diff->source_total }}</span>
                            <flux:icon.arrow-right class="!size-4 text-[var(--text-muted)]" />
                            <span class="text-[var(--text-muted)]">{{ __('V') }}{{ $diff->target_version_number }} {{ $diff->target_total }}</span>
                            <span class="s-badge {{ str_starts_with($diff->net_change, '-') ? 's-badge-red' : 's-badge-green' }}">
                                {{ __('Net') }} {{ $diff->net_change }}
                            </span>
                        </div>

                        @if(empty($diff->added) && empty($diff->removed) && empty($diff->changed))
                            <p class="text-sm text-[var(--text-muted)]">{{ __('These two versions have identical line items.') }}</p>
                        @else
                            <x-signals.table-wrap>
                                <table class="s-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Change') }}</th>
                                            <th>{{ __('Line') }}</th>
                                            <th class="text-right">{{ __('Qty') }}</th>
                                            <th class="text-right">{{ __('Unit') }}</th>
                                            <th class="text-right">{{ __('Total Δ') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($diff->added as $i => $line)
                                            <tr wire:key="diff-add-{{ $i }}">
                                                <td><span class="s-badge s-badge-green">{{ __('Added') }}</span></td>
                                                <td class="font-medium">{{ $line->name }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->target_quantity }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->target_unit_price }}</td>
                                                <td class="text-right text-[var(--green)]" style="font-family: var(--font-mono);">{{ $line->total_delta }}</td>
                                            </tr>
                                        @endforeach
                                        @foreach($diff->changed as $i => $line)
                                            <tr wire:key="diff-chg-{{ $i }}">
                                                <td><span class="s-badge s-badge-amber">{{ __('Changed') }}</span></td>
                                                <td class="font-medium">{{ $line->name }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->source_quantity }} → {{ $line->target_quantity }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->source_unit_price }} → {{ $line->target_unit_price }}</td>
                                                <td class="text-right {{ str_starts_with($line->total_delta, '-') ? 'text-[var(--red)]' : 'text-[var(--green)]' }}" style="font-family: var(--font-mono);">{{ $line->total_delta }}</td>
                                            </tr>
                                        @endforeach
                                        @foreach($diff->removed as $i => $line)
                                            <tr wire:key="diff-rem-{{ $i }}">
                                                <td><span class="s-badge s-badge-red">{{ __('Removed') }}</span></td>
                                                <td class="font-medium">{{ $line->name }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->source_quantity }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->source_unit_price }}</td>
                                                <td class="text-right text-[var(--red)]" style="font-family: var(--font-mono);">{{ $line->total_delta }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </x-signals.table-wrap>
                        @endif
                    </div>
                @else
                    <p class="mt-4 text-sm text-[var(--text-muted)]">{{ __('Select two different versions to compare their line items and totals.') }}</p>
                @endif
            </x-signals.panel>
        @endif

        </div>{{-- /LEFT column --}}

        {{-- ---------- RIGHT: lifecycle event timeline (read-only) ---------- --}}
        <div class="min-w-0 space-y-6">

        {{-- ============================================================ --}}
        {{--  TIMELINE (B4) — the opportunity's own lifecycle event        --}}
        {{--  history (action_logs), READ-ONLY, newest-first. No add.      --}}
        {{-- ============================================================ --}}
        <x-signals.panel title="{{ __('Timeline') }}">
            @if($timeline->isEmpty())
                <x-dense::empty-state
                    :title="__('No history yet')"
                    :description="__('The opportunity\'s lifecycle events — status changes, line items, dispatch and returns — appear here as they happen.')">
                    <x-slot:icon><flux:icon.clock class="!size-7" /></x-slot:icon>
                </x-dense::empty-state>
            @else
                <x-signals.timeline>
                    @foreach($timeline as $event)
                        <x-signals.timeline-item
                            :color="$event['color']"
                            :title="$event['title']"
                            wire:key="timeline-{{ $loop->index }}"
                        >
                            <x-slot:icon>
                                <flux:icon :icon="$event['icon']" class="!size-3.5" />
                            </x-slot:icon>

                            <div class="flex flex-col gap-0.5">
                                @if($event['detail'])
                                    <span class="font-medium text-[var(--text-default)]">{{ $event['detail'] }}</span>
                                @endif
                                <span class="text-[12px] text-[var(--text-muted)]">
                                    @if($event['at'])@localdatetime($event['at'])@endif
                                    @if($event['actor'])
                                        · {{ __('by') }} {{ $event['actor'] }}
                                    @endif
                                </span>
                            </div>
                        </x-signals.timeline-item>
                    @endforeach
                </x-signals.timeline>
            @endif
        </x-signals.panel>

        </div>{{-- /RIGHT column --}}

        </div>{{-- /two-column grid --}}
    </div>

    {{-- ============================================================ --}}
    {{--  CREATE-VERSION MODAL                                         --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="create-version" title="{{ __('Create version') }}"
        x-on:version-modal-close.window="if ($event.detail?.name === 'create-version') open = false">
        <div class="space-y-3">
            <div>
                <label for="create-type" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Type') }}</label>
                <select id="create-type" wire:model="createType" class="s-input w-full">
                    <option value="{{ \App\Domain\Projects\Opportunities\Enums\VersionType::Revision->value }}">{{ __('Revision (supersedes its parent)') }}</option>
                    <option value="{{ \App\Domain\Projects\Opportunities\Enums\VersionType::Alternative->value }}">{{ __('Alternative (coexists)') }}</option>
                </select>
            </div>
            <div>
                <label for="create-label" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Label (optional)') }}</label>
                <input id="create-label" type="text" wire:model="createLabel" class="s-input w-full" placeholder="{{ __('e.g. Budget option') }}" />
            </div>
            @if($versions->isNotEmpty())
                <div>
                    <label for="create-source" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Clone line items from') }}</label>
                    <select id="create-source" wire:model="createSourceId" class="s-input w-full">
                        <option value="">{{ __('Active version') }}</option>
                        @foreach($versions as $version)
                            <option value="{{ $version->id }}" wire:key="src-{{ $version->id }}">
                                V{{ $version->version_number }}{{ $version->label ? ' — '.$version->label : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'create-version')" class="s-btn s-btn-ghost">{{ __('Cancel') }}</button>
            <button type="button" wire:click="createVersion" class="s-btn s-btn-primary">{{ __('Create version') }}</button>
        </x-slot:footer>
    </x-signals.modal>

    {{-- ============================================================ --}}
    {{--  DECLINE MODAL                                                --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="decline-version" title="{{ __('Decline version') }}"
        x-on:version-modal-close.window="if ($event.detail?.name === 'decline-version') open = false">
        <div>
            <label for="decline-reason" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Reason (optional)') }}</label>
            <textarea id="decline-reason" wire:model="declineReason" rows="3" class="s-input w-full"></textarea>
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'decline-version')" class="s-btn s-btn-ghost">{{ __('Dismiss') }}</button>
            <button type="button" wire:click="submitDecline" class="s-btn s-btn-danger">{{ __('Decline version') }}</button>
        </x-slot:footer>
    </x-signals.modal>

    {{-- ============================================================ --}}
    {{--  RENAME MODAL                                                 --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="rename-version" title="{{ __('Rename version') }}"
        x-on:version-modal-close.window="if ($event.detail?.name === 'rename-version') open = false">
        <div>
            <label for="rename-label" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Label') }}</label>
            <input id="rename-label" type="text" wire:model="labelDraft" class="s-input w-full" placeholder="{{ __('Leave blank to clear') }}" />
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'rename-version')" class="s-btn s-btn-ghost">{{ __('Cancel') }}</button>
            <button type="button" wire:click="submitRename" class="s-btn s-btn-primary">{{ __('Save label') }}</button>
        </x-slot:footer>
    </x-signals.modal>
    @include('project-opportunities::partials.opportunity-action-modals')
</section>
