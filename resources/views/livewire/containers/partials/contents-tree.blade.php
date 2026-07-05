@props(['nodes', 'depth' => 0])

<ul class="{{ $depth > 0 ? 'ml-4 mt-2 border-l border-[var(--border)] pl-3' : '' }} space-y-1 text-sm">
    @foreach($nodes as $node)
        <li wire:key="tree-{{ $depth }}-{{ $node['type'] }}-{{ $node['uuid'] ?? $loop->index }}">
            @if($node['type'] === 'container')
                <a href="{{ route('containers.show', $node['uuid']) }}" wire:navigate class="font-medium text-[var(--link)] hover:underline">{{ $node['label'] }}</a>
                @if(! empty($node['children']))
                    @include('livewire.containers.partials.contents-tree', ['nodes' => $node['children'], 'depth' => $depth + 1])
                @endif
            @else
                <span>{{ $node['label'] }}</span>
            @endif
        </li>
    @endforeach
</ul>
