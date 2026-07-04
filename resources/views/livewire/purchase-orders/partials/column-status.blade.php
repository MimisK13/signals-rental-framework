@php
    $statuses = app(\App\Services\PurchaseOrders\PurchaseOrderStatusRegistry::class)->statuses();
    $badge = $statuses[$item->status]['badge_class'] ?? 's-badge-zinc';
@endphp
<span class="s-badge {{ $badge }}">{{ $statuses[$item->status]['label'] ?? $item->status }}</span>
