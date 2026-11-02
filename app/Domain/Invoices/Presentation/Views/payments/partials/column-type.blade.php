@if($item->amount < 0)
    <span class="s-badge s-badge-amber">Refund</span>
@else
    <span class="s-badge s-badge-green">Payment</span>
@endif
