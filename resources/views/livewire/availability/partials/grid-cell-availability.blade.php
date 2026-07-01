<div class="s-avail-group-cell s-avail-group-cell--{{ $cell['state'] }}"
     style="--used-pct: {{ $cell['used_pct'] }}%;"
     title="{{ __(':available available, :held held of :stock', ['available' => $cell['available'], 'held' => $cell['held'], 'stock' => $cell['stock']]) }}">
    <span class="s-avail-group-cell-available">{{ $cell['available'] }}</span>
    <span class="s-avail-group-cell-held">({{ $cell['held'] }})</span>
</div>
