{{--
    Availability-grid-shaped loading placeholder (group + bookings views).
    Uses the same table shell as the live grid so width stays full.
--}}
@include('livewire.availability.partials.grid-shell-open')
    <thead>
        <tr>
            <th class="s-avail-group-product-col s-avail-group-product-head">
                <x-signals.skeleton type="rect" width="70%" class="!h-4" />
            </th>
            @for($i = 0; $i < 7; $i++)
                <th wire:key="avail-skeleton-head-{{ $i }}" class="s-avail-group-day-col s-avail-group-head">
                    <x-signals.skeleton type="rect" width="60%" class="!mx-auto !h-4" />
                </th>
            @endfor
            <th class="s-avail-group-nav-col s-avail-group-nav-head"></th>
        </tr>
    </thead>
    <tbody>
        @for($row = 0; $row < 6; $row++)
            <tr wire:key="avail-skeleton-row-{{ $row }}">
                <th class="s-avail-group-product-col">
                    <x-signals.skeleton type="text" :lines="1" width="85%" />
                </th>
                @for($i = 0; $i < 7; $i++)
                    <td wire:key="avail-skeleton-cell-{{ $row }}-{{ $i }}" class="s-avail-group-day-col">
                        <x-signals.skeleton type="rect" width="100%" class="!mx-auto !h-8" />
                    </td>
                @endfor
                <td class="s-avail-group-nav-col"></td>
            </tr>
        @endfor
    </tbody>
@include('livewire.availability.partials.grid-shell-close')
