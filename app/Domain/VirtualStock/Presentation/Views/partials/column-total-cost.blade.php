@php $formatter = app(\App\Support\Formatter::class); @endphp
<span style="font-family: var(--font-mono);">{{ $formatter->money((int) $item->unit_cost * (int) $item->quantity, $item->currency_code) }}</span>
