@php $formatter = app(\App\Support\Formatter::class); @endphp
<span style="font-family: var(--font-mono);">{{ $formatter->money($item->amount_outstanding, $item->currency_code) }}</span>
