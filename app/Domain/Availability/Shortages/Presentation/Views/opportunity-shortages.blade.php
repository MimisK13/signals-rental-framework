<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Shortages', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'shortages'])

    @php
        $formatter = app(\App\Support\Formatter::class);
        $gateBadge = match($gate->decision) {
            \App\Domain\Availability\Shortages\Enums\ShortagePolicy::Block => 's-badge-red',
            \App\Domain\Availability\Shortages\Enums\ShortagePolicy::Warn => 's-badge-amber',
            \App\Domain\Availability\Shortages\Enums\ShortagePolicy::Allow => 's-badge-green',
        };
    @endphp

    <div class="flex-1 space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">

        @if(session('error'))
            <x-signals.alert type="danger">{{ session('error') }}</x-signals.alert>
        @endif
        @if(session('shortage_status'))
            <x-signals.alert type="success">{{ session('shortage_status') }}</x-signals.alert>
        @endif

        {{-- ============================================================ --}}
        {{--  CONVERT-TO-ORDER GATE PRE-CHECK (§7) + dispatch policy       --}}
        {{-- ============================================================ --}}
        <x-signals.panel title="Conversion & Dispatch Checks">
            <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
                <div class="rounded-md border p-4">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">Convert to Order gate</span>
                        <span class="s-badge {{ $gateBadge }}">{{ $gate->decision->label() }}</span>
                    </div>
                    <p class="text-sm text-[var(--text)]">
                        @switch($gate->decision)
                            @case(\App\Domain\Availability\Shortages\Enums\ShortagePolicy::Block)
                                {{ __('Conversion is blocked while unresolved shortages remain. Resolve them below (or obtain the permission to ignore shortages) before converting to an order.') }}
                                @break
                            @case(\App\Domain\Availability\Shortages\Enums\ShortagePolicy::Warn)
                                {{ __('Conversion will proceed but requires an acknowledgement of the outstanding shortages.') }}
                                @break
                            @default
                                {{ __('Shortages do not block conversion for this store; they remain visible below.') }}
                        @endswitch
                    </p>
                    <p class="mt-2 text-[11px] text-[var(--text-muted)]">
                        {{ __('Store policy') }}: <span class="font-semibold">{{ $gate->storePolicy->label() }}</span>
                        @if($gate->permissionUsed)
                            <span
                                class="s-badge s-badge-zinc s-badge-outline ml-1">{{ __('relaxed by your permission') }}</span>
                        @endif
                    </p>

                    @if($gate->acknowledgementRequired() && $canResolve)
                        <div class="mt-3 border-t pt-3">
                            <label for="ack-notes"
                                   class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Acknowledgement notes (optional)') }}</label>
                            <textarea id="ack-notes" wire:model="acknowledgeNotes" rows="2" class="s-input w-full"
                                      placeholder="{{ __('Recorded against the acknowledgement') }}"></textarea>
                            <button type="button" wire:click="acknowledgeShortages"
                                    class="s-btn s-btn-sm s-btn-warning mt-2">
                                <flux:icon.check-badge class="!size-3.5"/> {{ __('Acknowledge shortages') }}
                            </button>
                        </div>
                    @endif
                </div>

                <div class="rounded-md border p-4">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span
                            class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Dispatch policy') }}</span>
                        <span
                            class="s-badge {{ $dispatchPolicy === \App\Domain\Availability\Shortages\Enums\ShortageDispatchPolicy::Block ? 's-badge-red' : ($dispatchPolicy === \App\Domain\Availability\Shortages\Enums\ShortageDispatchPolicy::WarnPartial ? 's-badge-amber' : 's-badge-green') }}">
                            {{ $dispatchPolicy->label() }}
                        </span>
                    </div>
                    <p class="text-sm text-[var(--text)]">
                        @switch($dispatchPolicy)
                            @case(\App\Domain\Availability\Shortages\Enums\ShortageDispatchPolicy::Block)
                                {{ __('Dispatch cannot begin while any line is short. Short lines must be resolved before booking out.') }}
                                @break
                            @case(\App\Domain\Availability\Shortages\Enums\ShortageDispatchPolicy::WarnPartial)
                                {{ __('Available items dispatch as a partial shipment; short items are held back with a warning.') }}
                                @break
                            @default
                                {{ __('Available items dispatch silently; short items are held back without a warning.') }}
                        @endswitch
                    </p>
                </div>
            </div>
        </x-signals.panel>

        {{-- ============================================================ --}}
        {{--  BATCH RESOLVE ACROSS JOBS                                    --}}
        {{-- ============================================================ --}}
        @if(! empty($batchRows))
            <x-signals.panel title="{{ __('Resolve across jobs') }}">
                <p class="mb-3 text-sm text-[var(--text-muted)]">
                    {{ __('The same product is short on multiple jobs at this store. Consolidate into one sub-hire PO.') }}
                </p>
                <div class="space-y-3">
                    @foreach($batchRows as $batchIndex => $batchRow)
                        @php
                            $group = $batchRow['group'];
                        @endphp
                        <div wire:key="batch-group-{{ $group->productId }}-{{ $batchIndex }}"
                             class="rounded-md border p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-[var(--text)]">{{ $group->productName }}</div>
                                    <p class="text-[11px] text-[var(--text-muted)]">
                                        {{ __(':count jobs · :qty units total', ['count' => $group->opportunityCount(), 'qty' => $group->totalShortfall]) }}
                                    </p>
                                </div>
                            </div>
                            @if(empty($batchRow['resolvers']))
                                <p class="mt-2 text-[11px] text-[var(--text-muted)]">{{ __('No batch resolvers are applicable.') }}</p>
                            @else
                                <div class="mt-3 space-y-2">
                                    @foreach($batchRow['resolvers'] as $resolver)
                                        @foreach($resolver['options'] as $optionIndex => $option)
                                            <div
                                                wire:key="batch-opt-{{ $batchIndex }}-{{ $resolver['key'] }}-{{ $optionIndex }}"
                                                class="flex flex-wrap items-center justify-between gap-2 rounded border px-3 py-2">
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="text-sm font-medium text-[var(--text)]">{{ $option['label'] }}</span>
                                                        @if(! empty($option['metadata']['is_preferred']))
                                                            <span
                                                                class="s-badge s-badge-green s-badge-outline">{{ __('Preferred') }}</span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-0.5 text-[11px] text-[var(--text-muted)]">{{ $option['description'] }}</p>
                                                </div>
                                                @if($canResolve)
                                                    <button type="button"
                                                            wire:click="selectBatchResolution(@js($group->opportunityItemIds()), '{{ $resolver['key'] }}', {{ $optionIndex }})"
                                                            class="s-btn s-btn-sm s-btn-outline-blue shrink-0">
                                                        {{ __('Apply batch') }}
                                                    </button>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-signals.panel>
        @endif

        {{-- ============================================================ --}}
        {{--  SHORTAGE LIST + RESOLVER UI                                  --}}
        {{-- ============================================================ --}}
        <x-signals.panel title="Detected Shortages">
            @if(empty($shortageRows))
                <x-dense::empty-state
                    title="{{ __('No shortages') }}"
                    description="{{ __('Every line item on this opportunity is fully serviceable for its booking window.') }}">
                    <x-slot:icon>
                        <flux:icon.check-circle class="!size-7"/>
                    </x-slot:icon>
                </x-dense::empty-state>
            @else
                <div class="space-y-4">
                    @foreach($shortageRows as $row)
                        @php
                            /** @var \App\Domain\Availability\Shortages\ValueObjects\Shortage $shortage */
                            $shortage = $row['shortage'];
                            $remaining = $shortage->remainingShortfall();
                            $severityBadge = $shortage->isCritical ? 's-badge-red' : 's-badge-amber';
                        @endphp
                        <div wire:key="shortage-{{ $shortage->opportunityItemId }}" class="rounded-md border p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-[var(--text)]">
                                            {{ $shortage->productName }}
                                        </span>
                                        <span class="s-badge s-badge-zinc s-badge-outline">
                                            {{ $shortage->trackingType->label() }}
                                        </span>
                                        <span class="s-badge {{ $severityBadge }} s-badge-dot">
                                            {{ $shortage->isCritical ? __('Critical') : __('Shortfall') }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-[var(--text-muted)]">
                                        {{ __('Line item') }}
                                        #{{ $shortage->opportunityItemId }} &middot; {{ $this->windowLabel($shortage) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-4 text-right">
                                    <div>
                                        <div
                                            class="text-[11px] uppercase tracking-wide text-[var(--text-muted)]">{{ __('Requested') }}</div>
                                        <div class="text-sm font-semibold"
                                             style="font-family: var(--font-mono);">{{ $shortage->requestedQuantity }}</div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-[11px] uppercase tracking-wide text-[var(--text-muted)]">{{ __('Available') }}</div>
                                        <div class="text-sm font-semibold"
                                             style="font-family: var(--font-mono);">{{ $shortage->availableQuantity }}</div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-[11px] uppercase tracking-wide text-[var(--text-muted)]">{{ __('Short by') }}</div>
                                        <div class="text-sm font-semibold text-[var(--red)]"
                                             style="font-family: var(--font-mono);">{{ $shortage->shortfall }}</div>
                                    </div>

                                    @if($shortage->resolvedQuantity > 0)
                                        <div>
                                            <div class="text-[11px] uppercase tracking-wide text-[var(--text-muted)]">
                                                {{ __('Remaining') }}
                                            </div>
                                            <div class="text-sm font-semibold" style="font-family: var(--font-mono);">
                                                {{ $remaining }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @php
                                $availPercent = $shortage->requestedQuantity > 0
                                    ? min(100, (int) round($shortage->availableQuantity / $shortage->requestedQuantity * 100))
                                    : 0;
                            @endphp

                            <x-signals.qty-bar
                                class="mt-3"
                                :percent="$availPercent"
                                :label="$shortage->availableQuantity.' / '.$shortage->requestedQuantity.' '.__('available')"
                            />

                            {{-- Resolver options for this shortage --}}
                            <div class="mt-4 border-t pt-3">
                                @if($remaining <= 0)
                                    <p class="text-[11px] text-[var(--text-muted)]">{{ __('This shortfall is fully covered by active resolutions.') }}</p>
                                @elseif(empty($row['resolvers']))
                                    <p class="text-[11px] text-[var(--text-muted)]">{{ __('No resolvers are currently applicable to this shortage.') }}</p>
                                @else
                                    <div
                                        class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
                                        {{ __('Resolution options') }}
                                    </div>
                                    <div class="mt-2 space-y-2">
                                        @foreach($row['resolvers'] as $resolver)
                                            @foreach($resolver['options'] as $optionIndex => $option)
                                                <div
                                                    wire:key="opt-{{ $shortage->opportunityItemId }}-{{ $resolver['key'] }}-{{ $optionIndex }}"
                                                    class="flex flex-wrap items-center justify-between gap-2 rounded border px-3 py-2"
                                                >
                                                    <div class="min-w-0">
                                                        <div class="flex items-center gap-2">
                                                            <span
                                                                class="text-sm font-medium text-[var(--text)]">{{ $option['label'] }}</span>
                                                            @if($option['is_partial'])
                                                                <span
                                                                    class="s-badge s-badge-amber s-badge-outline">{{ __('Partial') }}</span>
                                                            @endif
                                                            @if($option['auto_executable'])
                                                                <span
                                                                    class="s-badge s-badge-zinc s-badge-outline">{{ __('Auto') }}</span>
                                                            @endif
                                                            @if(! empty($option['metadata']['is_preferred']))
                                                                <span
                                                                    class="s-badge s-badge-green s-badge-outline">{{ __('Preferred') }}</span>
                                                            @endif
                                                        </div>
                                                        <p class="mt-0.5 text-[11px] text-[var(--text-muted)]">{{ $option['description'] }}</p>
                                                        <div
                                                            class="mt-1 flex flex-wrap items-center gap-3 text-[11px] text-[var(--text-muted)]">
                                                            <span>{{ __('Covers') }}: <span
                                                                    class="font-semibold text-[var(--text)]">{{ $option['quantity_resolved'] }}</span></span>
                                                            @if($option['estimated_cost'] !== null)
                                                                <span>{{ __('Est. cost') }}: <span
                                                                        class="font-semibold text-[var(--text)]">{{ $formatter->money($option['estimated_cost']) }}</span></span>
                                                            @endif
                                                            @if($option['estimated_lead_time'] !== null)
                                                                <span>{{ __('Lead time') }}: <span
                                                                        class="font-semibold text-[var(--text)]">{{ $option['estimated_lead_time'] }} {{ __('min') }}</span></span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    @if($canResolve)
                                                        <button type="button"
                                                                wire:click="selectResolution({{ $shortage->opportunityItemId }}, '{{ $resolver['key'] }}', {{ $optionIndex }})"
                                                                class="s-btn s-btn-sm s-btn-outline-blue shrink-0"
                                                        >
                                                            {{ __('Apply') }}
                                                        </button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-signals.panel>

        {{-- ============================================================ --}}
        {{--  ACTIVE RESOLUTIONS + LIFECYCLE (§8.3)                        --}}
        {{-- ============================================================ --}}
        <x-signals.panel title="Resolutions">
            @if($resolutions->isEmpty())
                <p class="text-sm text-center text-[var(--text-muted)]">
                    {{ __('No resolutions have been recorded against this opportunity.') }}
                </p>
            @else
                <x-signals.table-wrap>
                    <table class="s-table">
                        <thead>
                        <tr>
                            <th>{{ __('Resolver') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th class="text-right">{{ __('Qty') }}</th>
                            <th class="text-right">{{ __('Cost') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($resolutions as $resolution)
                            @php
                                $statusBadge = match(true) {
                                    $resolution->status === \App\Domain\Availability\Shortages\Enums\ShortageResolutionStatus::Fulfilled => 's-badge-green',
                                    in_array($resolution->status, [\App\Domain\Availability\Shortages\Enums\ShortageResolutionStatus::Cancelled, \App\Domain\Availability\Shortages\Enums\ShortageResolutionStatus::Failed], true) => 's-badge-red',
                                    in_array($resolution->status, [\App\Domain\Availability\Shortages\Enums\ShortageResolutionStatus::Pending, \App\Domain\Availability\Shortages\Enums\ShortageResolutionStatus::Monitoring], true) => 's-badge-amber',
                                    default => 's-badge-blue',
                                };
                            @endphp
                            <tr wire:key="resolution-{{ $resolution->id }}">
                                <td class="font-medium">{{ $resolution->resolver_key }}</td>
                                <td>{{ $resolution->resolution_type->label() }}</td>
                                <td class="text-right"
                                    style="font-family: var(--font-mono);">{{ $resolution->quantity_resolved }}</td>
                                <td class="text-right"
                                    style="font-family: var(--font-mono);">{{ $resolution->cost !== null ? $formatter->money($resolution->cost) : '—' }}</td>
                                <td><span class="s-badge {{ $statusBadge }}">{{ $resolution->status->label() }}</span>
                                </td>
                                <td class="text-right">
                                    @if($canResolve && ! $resolution->status->isTerminal())
                                        <div class="inline-flex flex-wrap items-center justify-end gap-1">
                                            <button type="button"
                                                    @disabled(! $this->canTransition($resolution, 'confirmed'))
                                                    @if($this->canTransition($resolution, 'confirmed')) wire:click="confirmResolution({{ $resolution->id }})"
                                                    @endif
                                                    class="s-btn s-btn-xs s-btn-outline-blue {{ $this->canTransition($resolution, 'confirmed') ? '' : 'opacity-40' }}">
                                                {{ __('Confirm') }}
                                            </button>
                                            <button type="button"
                                                    @disabled(! $this->canTransition($resolution, 'in_progress'))
                                                    @if($this->canTransition($resolution, 'in_progress')) wire:click="startResolution({{ $resolution->id }})"
                                                    @endif
                                                    class="s-btn s-btn-xs s-btn-outline-blue {{ $this->canTransition($resolution, 'in_progress') ? '' : 'opacity-40' }}">
                                                {{ __('Start') }}
                                            </button>
                                            <button type="button"
                                                    @disabled(! $this->canTransition($resolution, 'fulfilled'))
                                                    @if($this->canTransition($resolution, 'fulfilled')) wire:click="fulfillResolution({{ $resolution->id }})"
                                                    @endif
                                                    class="s-btn s-btn-xs s-btn-outline-green {{ $this->canTransition($resolution, 'fulfilled') ? '' : 'opacity-40' }}">
                                                {{ __('Fulfill') }}
                                            </button>
                                            <button type="button"
                                                    @disabled(! $this->canTransition($resolution, 'cancelled'))
                                                    @if($this->canTransition($resolution, 'cancelled')) wire:click="promptReason({{ $resolution->id }}, 'cancel')"
                                                    x-on:click="$dispatch('open-modal', 'transition-reason')" @endif
                                                    class="s-btn s-btn-xs s-btn-ghost {{ $this->canTransition($resolution, 'cancelled') ? '' : 'opacity-40' }}">
                                                {{ __('Cancel') }}
                                            </button>
                                            <button type="button"
                                                    @disabled(! $this->canTransition($resolution, 'failed'))
                                                    @if($this->canTransition($resolution, 'failed')) wire:click="promptReason({{ $resolution->id }}, 'fail')"
                                                    x-on:click="$dispatch('open-modal', 'transition-reason')" @endif
                                                    class="s-btn s-btn-xs s-btn-ghost {{ $this->canTransition($resolution, 'failed') ? '' : 'opacity-40' }}">
                                                {{ __('Fail') }}
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-[var(--text-muted)]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </x-signals.table-wrap>
            @endif
        </x-signals.panel>
    </div>

    {{-- ============================================================ --}}
    {{--  APPLY-RESOLUTION MODAL                                       --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="apply-resolution" title="{{ __('Apply resolution') }}"
                     x-on:shortage-modal-close.window="if ($event.detail?.name === 'apply-resolution') open = false">
        @if($appliedOption !== null)
            <div class="space-y-3">
                <div>
                    <div class="text-sm font-semibold text-[var(--text)]">{{ $appliedOption['label'] }}</div>
                    <p class="mt-1 text-[13px] text-[var(--text-muted)]">{{ $appliedOption['description'] }}</p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-[12px]">
                    <div><span class="text-[var(--text-muted)]">{{ __('Covers') }}:</span> <span
                            class="font-semibold">{{ $appliedOption['quantity_resolved'] }}</span></div>
                    @if($appliedOption['estimated_cost'] !== null)
                        <div><span class="text-[var(--text-muted)]">{{ __('Est. cost') }}:</span> <span
                                class="font-semibold">{{ app(\App\Support\Formatter::class)->money($appliedOption['estimated_cost']) }}</span>
                        </div>
                    @endif
                </div>
                <div>
                    <label for="apply-notes" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
                        {{ __('Notes (optional)') }}
                    </label>
                    <textarea id="apply-notes" wire:model="applyNotes" rows="2" class="s-input w-full"></textarea>
                </div>
            </div>
        @else
            <p class="text-sm text-[var(--text-muted)]">
                {{ __('This option is no longer available — the shortage may have changed.') }}
            </p>
        @endif

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'apply-resolution')" class="s-btn s-btn-ghost">
                {{ __('Cancel') }}
            </button>
            <button type="button" wire:click="applyResolution" @disabled($appliedOption === null) class="s-btn s-btn-primary">
                {{ __('Apply resolution') }}
            </button>
        </x-slot:footer>
    </x-signals.modal>

    {{-- ============================================================ --}}
    {{--  APPLY-BATCH-RESOLUTION MODAL                                 --}}
    {{-- ============================================================ --}}
    <x-signals.modal
        name="apply-batch-resolution"
        title="{{ __('Apply batch resolution') }}"
        x-on:shortage-modal-close.window="if ($event.detail?.name === 'apply-batch-resolution') open = false"
    >
        @if($appliedBatchOption !== null)
            <div class="space-y-3">
                <div>
                    <div class="text-sm font-semibold text-[var(--text)]">
                        {{ $appliedBatchOption['label'] }}
                    </div>
                    <p class="mt-1 text-[13px] text-[var(--text-muted)]">
                        {{ $appliedBatchOption['description'] }}
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-[12px]">
                    <div>
                        <span class="text-[var(--text-muted)]">
                            {{ __('Covers') }}:
                        </span>
                        <span class="font-semibold">
                            {{ $appliedBatchOption['quantity_resolved'] }}
                        </span>
                    </div>

                    @if($appliedBatchOption['estimated_cost'] !== null)
                        <div>
                            <span class="text-[var(--text-muted)]">
                                {{ __('Est. cost') }}:
                            </span>
                            <span class="font-semibold">
                                {{ app(\App\Support\Formatter::class)->money($appliedBatchOption['estimated_cost']) }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <p class="text-sm text-[var(--text-muted)]">
                {{ __('This batch option is no longer available.') }}
            </p>
        @endif

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'apply-batch-resolution')" class="s-btn s-btn-ghost">
                {{ __('Cancel') }}
            </button>
            <button type="button" wire:click="applyBatchResolution" @disabled($appliedBatchOption === null) class="s-btn s-btn-primary">
                {{ __('Apply batch resolution') }}
            </button>
        </x-slot:footer>
    </x-signals.modal>

    {{-- ============================================================ --}}
    {{--  CANCEL / FAIL REASON MODAL                                   --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="transition-reason" title="{{ __('Reason') }}"
                     x-on:shortage-modal-close.window="if ($event.detail?.name === 'transition-reason') open = false">
        <div>
            <label for="transition-reason"
                   class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
                {{ $reasonTransition === 'fail' ? __('Why did this resolution fail? (optional)') : __('Why are you cancelling? (optional)') }}
            </label>
            <textarea id="transition-reason" wire:model="transitionReason" rows="3" class="s-input w-full"></textarea>
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'transition-reason')"
                    class="s-btn s-btn-ghost">{{ __('Dismiss') }}</button>
            <button type="button" wire:click="submitReasonTransition" class="s-btn s-btn-danger">
                {{ $reasonTransition === 'fail' ? __('Mark failed') : __('Cancel resolution') }}
            </button>
        </x-slot:footer>
    </x-signals.modal>

    @include('livewire.opportunities.partials.opportunity-action-modals')
</section>

