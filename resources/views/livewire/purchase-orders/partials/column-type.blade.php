@php $types = config('purchase_orders.types', []); @endphp
<span class="s-badge s-badge-zinc">{{ $types[$item->po_type]['label'] ?? $item->po_type }}</span>
