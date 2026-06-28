{{--
    Planner overlap-grid loading placeholder — full-width panel matching the live grid.
--}}
<div class="s-panel relative w-full min-w-0 !p-0 overflow-hidden border-t-0 !rounded-t-none" aria-hidden="true">
    <div class="flex w-full border-b border-[var(--card-border)]">
        @for($i = 0; $i < 7; $i++)
            <div class="flex flex-1 items-center justify-center px-2 py-2" wire:key="planner-skeleton-head-{{ $i }}">
                <x-signals.skeleton type="rect" width="50%" class="!h-4" />
            </div>
        @endfor
    </div>
    @for($lane = 0; $lane < 4; $lane++)
        <div class="relative w-full px-2 py-2" wire:key="planner-skeleton-lane-{{ $lane }}">
            <x-signals.skeleton type="rect" width="{{ 40 + ($lane * 10) }}%" class="!h-12" />
        </div>
    @endfor
</div>
