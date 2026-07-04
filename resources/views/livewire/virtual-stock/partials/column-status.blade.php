@php $statusMeta = app(\App\Services\VirtualStock\VirtualStockStatusRegistry::class)->statuses()[$item->status] ?? []; @endphp
<span class="s-badge {{ $statusMeta['badge_class'] ?? 's-badge-zinc' }}">{{ $statusMeta['label'] ?? $item->status }}</span>
