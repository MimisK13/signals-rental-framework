@props(['nodes', 'depth' => 0, 'canUnpack' => false, 'canTransfer' => false])

@foreach($nodes as $node)
    <tr wire:key="tree-{{ $depth }}-{{ $node['type'] }}-{{ $node['uuid'] ?? $node['serialised_item_id'] ?? $loop->index }}">
        <td>
            <span class="s-badge {{ $node['type'] === 'container' ? 's-badge-blue' : 's-badge-zinc' }}">
                {{ $node['type'] === 'container' ? 'Container' : 'Serialized item' }}
            </span>
        </td>
        <td>
            <div style="padding-left: {{ $depth * 1.25 }}rem;">
                @if($node['type'] === 'container')
                    <a href="{{ route('containers.show', $node['uuid']) }}" wire:navigate.hover class="font-medium text-[var(--link)] hover:underline">{{ $node['label'] }}</a>
                @elseif($node['asset_id'] ?? null)
                    <a href="{{ route('assets.show', $node['asset_id']) }}" wire:navigate.hover class="font-medium text-[var(--link)] hover:underline">{{ $node['label'] }}</a>
                @else
                    <span>{{ $node['label'] }}</span>
                @endif
            </div>
        </td>
        @if($canUnpack || $canTransfer)
            <td class="text-right">
                @if(($canUnpack || $canTransfer) && $node['type'] === 'item' && ($node['serialised_item_id'] ?? null))
                <div
                    x-data="{ open: false }"
                    x-on:keydown.escape.prevent.stop="open = false"
                    class="relative inline-flex"
                >
                    <button
                        type="button"
                        class="s-btn-ghost s-btn-xs s-btn-icon"
                        aria-label="Actions"
                        title="Actions"
                        :aria-expanded="open"
                        x-on:click="open = ! open"
                    >
                        <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                            <circle cx="12" cy="5" r="1.5" />
                            <circle cx="12" cy="12" r="1.5" />
                            <circle cx="12" cy="19" r="1.5" />
                        </svg>
                    </button>
                    <div
                        x-ref="panel"
                        x-show="open"
                        x-cloak
                        x-on:click.outside="open = false"
                        class="s-dropdown"
                        style="position: fixed; z-index: 9999; min-width: 150px;"
                        x-init="$watch('open', value => {
                            if (value) {
                                $nextTick(() => {
                                    const rect = $el.previousElementSibling.getBoundingClientRect();
                                    $el.style.top = rect.bottom + 4 + 'px';
                                    $el.style.right = (window.innerWidth - rect.right) + 'px';
                                    $el.style.left = 'auto';
                                });
                            }
                        })"
                    >
                        @if($canUnpack)
                            <button
                                type="button"
                                class="s-dropdown-item w-full text-left"
                                x-on:click="open = false; $dispatch('open-modal', 'unpack-container-item-confirm')"
                                wire:click="promptUnpackFromContents({{ $node['serialised_item_id'] }})"
                            >
                                Unpack
                            </button>
                        @endif
                        @if($canTransfer)
                            <button
                                type="button"
                                class="s-dropdown-item w-full text-left"
                                x-on:click="open = false; $dispatch('open-modal', 'transfer-container-item')"
                                wire:click="promptTransferFromContents({{ $node['serialised_item_id'] }})"
                            >
                                Transfer
                            </button>
                        @endif
                    </div>
                </div>
                @endif
            </td>
        @endif
    </tr>

    @if(! empty($node['children']))
        @include('livewire.containers.partials.contents-tree', ['nodes' => $node['children'], 'depth' => $depth + 1, 'canUnpack' => $canUnpack, 'canTransfer' => $canTransfer])
    @endif
@endforeach
