{{--
    By-product-group availability grid: a group's products (rows) x dates (columns).

    Table chrome is shared with the product bookings view via grid-* partials.
--}}
@php
    /** @var array{rows: list<array<string, mixed>>, paginator: \Illuminate\Contracts\Pagination\LengthAwarePaginator} $grid */
    $grid = $this->groupRows;
    $rows = $grid['rows'];
    $paginator = $grid['paginator'];
    $dayList = $this->groupDays;
    $fromDate = $this->from !== '' ? $this->from : \Illuminate\Support\Carbon::today('UTC')->toDateString();
    $bookingsStoreIds = $this->storeIds;
@endphp

@if($groupId === null)
    <x-dense::empty-state
        title="{{ __('Select a product group') }}"
        description="{{ __('Choose a product group to view the availability of its products over the period.') }}">
        <x-slot:icon><flux:icon.squares-2x2 class="!size-7" /></x-slot:icon>
    </x-dense::empty-state>
@elseif(empty($rows))
    <x-dense::empty-state
        title="{{ __('No matching products') }}"
        description="{{ __('No products in this group match the current filters, or none have availability data at this store for the chosen period.') }}">
        <x-slot:icon><flux:icon.calendar class="!size-7" /></x-slot:icon>
    </x-dense::empty-state>
@else
    @include('livewire.availability.partials.grid-shell-open')
        @include('livewire.availability.partials.grid-header', [
            'dayList' => $dayList,
            'labelColumn' => __('Product'),
            'keyPrefix' => 'ghead',
        ])
        <tbody>
            @foreach($rows as $row)
                <tr wire:key="grow-{{ $row['product_id'] }}">
                    @include('livewire.availability.partials.grid-label', [
                        'labelHtml' => view('livewire.availability.partials.grid-label-product', [
                            'productId' => $row['product_id'],
                            'productName' => $row['product_name'] ?? __('Product #:id', ['id' => $row['product_id']]),
                            'fromDate' => $fromDate,
                            'daysPeriod' => $this->daysPeriod,
                            'storeIds' => $bookingsStoreIds,
                        ])->render(),
                    ])
                    @foreach($row['cells'] as $cell)
                        @include('livewire.availability.partials.grid-day-cell', [
                            'mode' => 'availability',
                            'date' => $cell['date'],
                            'cell' => $cell,
                            'wireKey' => 'gcell-'.$row['product_id'].'-'.$cell['date'],
                            'bookingsUrl' => route('availability.bookings', array_filter([
                                'product' => $row['product_id'],
                                'from' => $fromDate,
                                'days' => $this->daysPeriod,
                                'stores' => $bookingsStoreIds !== [] ? $bookingsStoreIds : null,
                            ], static fn (mixed $value): bool => $value !== null)),
                        ])
                    @endforeach
                    @include('livewire.availability.partials.grid-nav-spacer')
                </tr>
            @endforeach
        </tbody>
    @include('livewire.availability.partials.grid-shell-close')

    @include('livewire.availability.partials.grid-legend')

    @if($paginator->hasPages())
        <div class="mt-4">
            {{ $paginator->links() }}
        </div>
    @endif
@endif
