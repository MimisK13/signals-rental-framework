{{--
    Availability-grid-shaped loading placeholder (group + bookings views).
--}}
<div class="s-avail-group-table-wrap" aria-hidden="true">
    <div class="s-panel !p-0 overflow-hidden">
        <div class="flex border-b border-[var(--card-border)]">
            <div class="shrink-0 px-3 py-2" style="width: 12rem;">
                <x-signals.skeleton type="rect" width="70%" class="!h-4" />
            </div>
            @for($i = 0; $i < 7; $i++)
                <div class="flex flex-1 items-center justify-center border-l border-[var(--card-border)] px-2 py-2" wire:key="avail-skeleton-head-{{ $i }}">
                    <x-signals.skeleton type="rect" width="60%" class="!h-4" />
                </div>
            @endfor
        </div>
        @for($row = 0; $row < 6; $row++)
            <div class="flex border-b border-[var(--card-border)]" wire:key="avail-skeleton-row-{{ $row }}">
                <div class="shrink-0 px-3 py-3" style="width: 12rem;">
                    <x-signals.skeleton type="text" :lines="1" width="85%" />
                </div>
                @for($i = 0; $i < 7; $i++)
                    <div class="flex flex-1 items-center justify-center border-l border-[var(--card-border)] p-2" wire:key="avail-skeleton-cell-{{ $row }}-{{ $i }}">
                        <x-signals.skeleton type="rect" width="100%" class="!h-8" />
                    </div>
                @endfor
            </div>
        @endfor
    </div>
</div>
