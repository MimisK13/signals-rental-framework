<section class="w-full" data-density="compact">
    <x-dense::page-header :title="$container->name">
        <x-slot:meta>
            <a href="{{ route('containers.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Containers</a>
        </x-slot:meta>
        <x-slot:actions>
            @if($repackOpportunityId)
                <x-dense::button href="{{ route('containers.repack', ['container' => $container, 'opportunity' => $repackOpportunityId]) }}" wire:navigate.hover variant="ghost" size="sm">Repack session</x-dense::button>
            @endif
        </x-slot:actions>
    </x-dense::page-header>

    @if(session('container_status'))
        <div class="px-6 pt-4 max-md:px-5 max-sm:px-3">
            <x-signals.alert type="success">{{ session('container_status') }}</x-signals.alert>
        </div>
    @endif

    <div class="px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0 space-y-4">
        <x-dense::card title="Physical Identity" class="shadow-none">
            <x-signals.data-list layout="grid" :dividers="false" :items="array_filter([
                ['label' => 'Barcode', 'value' => $container->barcode ?? '—', 'mono' => true],
                ['label' => 'Store', 'value' => $container->store?->name ?? '—'],
                ['label' => 'Status', 'value' => $container->status->label(), 'badge' => 's-badge-zinc'],
                ['label' => 'Mode', 'value' => $container->availabilityMode()->label(), 'badge' => match($container->availabilityMode()) {
                    \App\Domain\Warehouse\Containers\Enums\ContainerAvailabilityMode::Transport => 's-badge-green',
                    \App\Domain\Warehouse\Containers\Enums\ContainerAvailabilityMode::Kit => 's-badge-blue',
                    \App\Domain\Warehouse\Containers\Enums\ContainerAvailabilityMode::Hybrid => 's-badge-violet',
                }],
                ['label' => 'Scan Mode', 'value' => $container->scan_mode->label(), 'badge' => $container->scan_mode === \App\Domain\Warehouse\Containers\Enums\ContainerScanMode::Free ? 's-badge-green' : 's-badge-violet'],
                $checkinModeLabel ? ['label' => 'Check-in mode', 'value' => $checkinModeLabel] : null,
            ])" />

            @if($needsCheckinVerification)
                <div class="mt-3">
                    <span class="s-badge s-badge-amber">Verify check-in</span>
                </div>
            @endif

        </x-dense::card>

        {{-- Legacy Details markup retained as a reference during the Physical Identity card migration. --}}
        {{--
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Barcode</div>
                    <div style="font-family: var(--font-mono);">{{ $container->barcode ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Status</div>
                    <span class="s-badge s-badge-zinc">{{ $container->status->label() }}</span>
                    @if($needsCheckinVerification)
                        <span class="s-badge s-badge-amber ml-2">Verify check-in</span>
                    @endif
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Mode</div>
                    <span class="s-badge {{ match($container->availabilityMode()) {
                        \App\Domain\Warehouse\Containers\Enums\ContainerAvailabilityMode::Transport => 's-badge-green',
                        \App\Domain\Warehouse\Containers\Enums\ContainerAvailabilityMode::Kit => 's-badge-blue',
                        \App\Domain\Warehouse\Containers\Enums\ContainerAvailabilityMode::Hybrid => 's-badge-violet',
                    } }}">
                        {{ $container->availabilityMode()->label() }}
                    </span>
                </div>
                @if($checkinModeLabel)
                    <div>
                        <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Check-in mode</div>
                        <div>{{ $checkinModeLabel }}</div>
                    </div>
                @endif
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Scan mode</div>
                    <span class="s-badge {{ $container->scan_mode === \App\Domain\Warehouse\Containers\Enums\ContainerScanMode::Free ? 's-badge-green' : 's-badge-violet' }}">
                        {{ $container->scan_mode->label() }}
                    </span>
                    @if($canEdit)
                        <form wire:submit="saveScanMode" class="mt-1 flex flex-wrap items-end gap-2">
                            <x-dense::select id="container-scan-mode" wire:model="editScanMode" class="min-w-[8rem]">
                                @foreach($scanModes as $mode)
                                    <option value="{{ $mode->value }}" wire:key="scan-mode-{{ $mode->value }}">{{ $mode->label() }}</option>
                                @endforeach
                            </x-dense::select>
                            <x-dense::button type="submit" size="sm" wire:loading.attr="disabled" wire:target="saveScanMode">{{ __('Save') }}</x-dense::button>
                        </form>
                    @endif
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Store</div>
                    <div>{{ $container->store?->name ?? '—' }}</div>
                </div>
            </div>
        --}}

        @if($dispatchPrompt)
            <x-dense::card class="shadow-none">
                <h2 class="text-sm font-semibold mb-2">Dispatch requires opportunity amendments</h2>
                <p class="text-sm text-[var(--muted)] mb-3">{{ $dispatchPrompt['message'] ?? '' }}</p>
                <div class="space-y-2 mb-4">
                    @foreach($dispatchPrompt['amendments'] ?? [] as $amendment)
                        <div class="text-sm border border-[var(--border)] rounded-md p-3" wire:key="dispatch-amendment-{{ $amendment['product_id'] }}">
                            <div class="font-medium">{{ $amendment['product_name'] ?? 'Product #'.$amendment['product_id'] }}</div>
                            <div class="text-[var(--muted)]">
                                {{ str($amendment['reason'] ?? '')->headline() }} —
                                suggest {{ $amendment['required_action'] ?? 'add' }}
                                × {{ $amendment['suggested_quantity'] ?? '1' }}
                            </div>
                        </div>
                    @endforeach
                    @foreach($dispatchPrompt['validation'] ?? [] as $row)
                        <div class="text-xs text-[var(--muted)] font-mono" wire:key="dispatch-validation-{{ $row['product_id'] ?? $loop->index }}">
                            {{ $row['product_name'] ?? '' }}: {{ $row['verdict'] ?? '' }} (container {{ $row['container_quantity'] ?? '?' }} / opportunity {{ $row['opportunity_quantity'] ?? '?' }})
                        </div>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($canApplyDispatchAmendments)
                        <flux:button
                            wire:click="confirmDispatchAmendments({{ json_encode(array_map(
                                static fn (array $row): array => [
                                    'product_id' => $row['product_id'],
                                    'action' => $row['required_action'] ?? 'add',
                                    'quantity' => $row['suggested_quantity'] ?? '1',
                                    'price' => $row['suggested_price'] ?? null,
                                ],
                                $dispatchPrompt['amendments'] ?? [],
                            )) }})"
                            wire:loading.attr="disabled"
                            wire:target="confirmDispatchAmendments"
                            variant="primary"
                        >
                            Apply amendments and dispatch
                        </flux:button>
                    @endif
                    <x-dense::button wire:click="dismissDispatchPrompt" wire:loading.attr="disabled" wire:target="dismissDispatchPrompt" size="sm">Dismiss</x-dense::button>
                </div>
            </x-dense::card>
        @endif

        @if($canReturn)
            <x-dense::card class="shadow-none">
                <h2 class="text-sm font-semibold mb-3">Return kit</h2>
                        <x-dense::button wire:click="returnKit" wire:loading.attr="disabled" wire:target="returnKit" variant="primary" size="sm">Return kit</x-dense::button>
            </x-dense::card>
        @endif

        @if($canVerifyCheckin)
            <x-dense::card class="shadow-none">
                <h2 class="text-sm font-semibold mb-3">Check-in verification</h2>
                <x-dense::button wire:click="verifyCheckin" wire:loading.attr="disabled" wire:target="verifyCheckin" variant="primary" size="sm">Mark verified</x-dense::button>
            </x-dense::card>
        @endif

        @if($canPack && $container->status->acceptsPacking())
            <div class="flex flex-wrap items-center gap-2">
                <x-dense::button href="{{ route('containers.pack', $container) }}" wire:navigate.hover variant="primary" size="sm">Pack items</x-dense::button>
                <button type="button" x-on:click="$dispatch('open-modal', 'container-pack-modal')" class="button button-ghost button-sm">Quick pack</button>
            </div>
        @endif

        <x-dense::table-card title="Contents" class="shadow-none">
            @if($this->contentsTree() === [])
                <x-dense::empty-state title="No items packed" description="Pack items to build this Container's contents." />
            @else
                <table class="s-table s-table-compact w-full">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Item</th>
                            @if(($canPack || $canTransfer) && $container->status->acceptsPacking())
                                <th class="text-right" style="text-align: right;">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @include('containers::partials.contents-tree', ['nodes' => $this->contentsTree(), 'depth' => 0, 'canUnpack' => $canPack && $container->status->acceptsPacking(), 'canTransfer' => $canTransfer && $container->status->acceptsPacking()])
                    </tbody>
                </table>
            @endif
        </x-dense::table-card>

        {{-- TODO: This is a legacy execution hook. The future planning entry
             point is Quotation/Order, where Containers and Kits are selected
             before warehouse dispatch. --}}
        @if($canDispatch)
            <x-dense::card class="shadow-none">
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="text-sm font-semibold">Dispatch to opportunity</h2>
                    <span class="s-badge s-badge-amber">TODO</span>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[12rem]">
                        <x-dense::field-label for="container-opportunity-id">Opportunity ID</x-dense::field-label>
                        <x-dense::input id="container-opportunity-id" wire:model="dispatchOpportunityId" type="number" min="1" />
                    </div>
                    <x-dense::button wire:click="dispatchContainer" wire:loading.attr="disabled" wire:target="dispatchContainer" variant="primary" size="sm">Dispatch</x-dense::button>
                </div>
            </x-dense::card>
        @endif

            </div>

            <aside class="min-w-0 space-y-4">
        @if($canDissolve || $canEdit || ($canSeal && $container->status === \App\Domain\Warehouse\Containers\Enums\ContainerStatus::Sealed))
            <div class="space-y-3">
                @if($canSeal && $container->status === \App\Domain\Warehouse\Containers\Enums\ContainerStatus::Open)
                    <x-dense::button wire:click="seal" wire:loading.attr="disabled" wire:target="seal" variant="primary" size="lg" class="w-full">Seal</x-dense::button>
                @endif

                @if($canSeal && $container->status === \App\Domain\Warehouse\Containers\Enums\ContainerStatus::Sealed)
                    <div>
                        <x-dense::field-label for="container-unseal-reason">Unseal reason</x-dense::field-label>
                        <div class="relative">
                            <x-dense::select id="container-unseal-reason" wire:model="unsealReason" class="w-full appearance-none pr-9">
                                @foreach($unsealReasons as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </x-dense::select>
                            <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]">
                                <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    </div>

                    <x-dense::button wire:click="unseal" wire:loading.attr="disabled" wire:target="unseal" wire:confirm="Unseal this container and allow its contents to be changed?" size="lg" class="w-full">Unseal</x-dense::button>
                @endif

                @if($canDissolve)
                    <x-dense::button wire:click="dissolve" wire:loading.attr="disabled" wire:target="dissolve" wire:confirm="Dissolve this container and release all contents?" variant="danger" size="lg" class="w-full">Dissolve</x-dense::button>
                @endif

                @if($canEdit)
                    <div class="space-y-2">
                        <x-dense::field-label>Scan mode</x-dense::field-label>
                        <div class="inline-flex w-full gap-1" role="tablist" aria-label="Scan mode">
                            @foreach($scanModes as $mode)
                                <x-dense::button
                                    type="button"
                                    wire:click="setScanMode('{{ $mode->value }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="setScanMode"
                                    variant="{{ $container->scan_mode === $mode ? 'primary' : 'ghost' }}"
                                    size="sm"
                                    role="tab"
                                    aria-selected="{{ $container->scan_mode === $mode ? 'true' : 'false' }}"
                                    class="flex-1"
                                >
                                    {{ $mode->label() }}
                                </x-dense::button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-dense::card title="Lifecycle" class="shadow-none">
            @if($timeline->isEmpty())
                <p class="text-sm text-[var(--muted)]">No audit events recorded yet.</p>
            @else
                <x-signals.timeline>
                    @foreach($timeline as $entry)
                        <x-signals.timeline-item
                            wire:key="timeline-{{ $loop->index }}"
                            :color="$entry['color']"
                            :title="$entry['title']"
                            :meta="$entry['meta']"
                        >
                            @if($entry['body'])
                                <p class="text-sm text-[var(--muted)]">{{ $entry['body'] }}</p>
                            @endif
                        </x-signals.timeline-item>
                    @endforeach
                </x-signals.timeline>
            @endif
        </x-dense::card>
            </aside>
        </div>
    </div>

    <x-signals.modal id="container-pack-modal" name="container-pack-modal" title="Quick Pack" size="lg" :teleport="false">
        <div class="space-y-4" data-density="compact">
            <form wire:submit="packFromDrawer" class="space-y-2">
                <div>
                    <x-dense::field-label for="container-drawer-scan-input">Asset number, serial number, or ID</x-dense::field-label>
                    <x-dense::input
                        id="container-drawer-scan-input"
                        wire:model="drawerScanInput"
                        class="w-full font-mono"
                        placeholder="Scan or enter item identity"
                        autocomplete="off"
                        aria-describedby="container-drawer-scan-input-error"
                    />
                </div>
                @error('drawerScanInput')
                    <p id="container-drawer-scan-input-error" class="dense-field-error" role="alert">{{ $message }}</p>
                @enderror
                <x-dense::button type="submit" wire:loading.attr="disabled" wire:target="packFromDrawer" variant="primary" size="lg" class="w-full">Pack item</x-dense::button>
            </form>

            @if($drawerPackConflict)
                <div class="border-t border-[var(--border)] pt-4">
                    <div class="text-sm font-semibold">Packing conflict</div>
                    <p class="mt-1 text-sm text-[var(--muted)]">{{ str($drawerPackConflict['conflict'] ?? '')->headline() }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($drawerPackConflict['resolutions'] ?? [] as $key => $resolution)
                            @if($resolution['available'] ?? false)
                                <x-dense::button wire:click="resolveDrawerPackConflict('{{ $key }}')" wire:loading.attr="disabled" wire:target="resolveDrawerPackConflict" variant="primary" size="sm">
                                    {{ $resolution['description'] ?? str($key)->headline() }}
                                </x-dense::button>
                            @endif
                        @endforeach
                        <x-dense::button wire:click="dismissDrawerPackConflict" wire:loading.attr="disabled" wire:target="dismissDrawerPackConflict" variant="ghost" size="sm">Dismiss</x-dense::button>
                    </div>
                </div>
            @endif

        </div>
    </x-signals.modal>

    <x-signals.modal name="transfer-container-item" title="Transfer item" size="md" :teleport="false">
        <div class="space-y-3">
            <p class="text-sm text-[var(--muted)]">
                Move the selected serialized item from this Container to another Container.
            </p>

            <div>
                <x-dense::field-label for="container-transfer-target">Destination Container</x-dense::field-label>
                <x-signals.combobox
                    name="transferTargetContainer"
                    input-id="container-transfer-target"
                    :value="$transferTargetUuid ?: null"
                    :options="$transferContainerOptions"
                    placeholder="Search Containers by name or barcode"
                    class="w-full"
                    x-on:combobox-selected.window="if ($event.detail.name === 'transferTargetContainer') $wire.set('transferTargetUuid', $event.detail.value ?? '')"
                />
                @error('transferTargetUuid')
                    <p id="container-transfer-target-error" class="dense-field-error" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <x-slot:footer>
            <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('close-modal', 'transfer-container-item')">
                Cancel
            </button>
            <button type="button" class="s-btn s-btn-primary" wire:click="confirmTransferFromContents" wire:loading.attr="disabled" wire:target="confirmTransferFromContents">
                Transfer
            </button>
        </x-slot:footer>
    </x-signals.modal>

    <x-signals.modal name="unpack-container-item-confirm" title="Unpack item" size="sm" :teleport="false">
    <p class="text-sm text-[var(--muted)]">
        This will remove the serialized item from the Container. The inventory identity and quantity will not be changed.
    </p>

    <x-slot:footer>
        <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('close-modal', 'unpack-container-item-confirm')">
            Cancel
        </button>
        <button type="button" class="s-btn s-btn-danger" wire:click="confirmUnpackFromContents" wire:loading.attr="disabled" wire:target="confirmUnpackFromContents">
            Unpack
        </button>
    </x-slot:footer>
    </x-signals.modal>
</section>
