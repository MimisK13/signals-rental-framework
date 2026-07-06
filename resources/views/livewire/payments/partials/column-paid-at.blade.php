@if($item->paid_at)
    @localdatetime($item->paid_at)
@else
    —
@endif
