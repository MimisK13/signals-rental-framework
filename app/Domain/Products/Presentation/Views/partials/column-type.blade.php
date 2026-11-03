@php
    $typeBadgeClass = match($item->product_type) {
        \App\Domain\Products\Enums\ProductType::Rental => 's-badge-blue',
        \App\Domain\Products\Enums\ProductType::Sale => 's-badge-green',
        \App\Domain\Products\Enums\ProductType::Service => 's-badge-amber',
        \App\Domain\Products\Enums\ProductType::LossAndDamage => 's-badge-red',
        default => 's-badge-zinc',
    };
@endphp
<span class="s-badge {{ $typeBadgeClass }}">{{ $item->product_type->label() }}</span>
