@php $formatter = app(\App\Support\Formatter::class); @endphp
<span style="font-family: var(--font-mono);">{{ $formatter->money(abs($item->total), $item->currency_code) }}</span>
