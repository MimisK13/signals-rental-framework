@if($item->issued_at)
    @localdate($item->issued_at)
@else
    —
@endif
