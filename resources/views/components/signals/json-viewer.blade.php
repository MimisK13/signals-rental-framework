@props([
    'copy' => false,
    'compact' => false,
    'content' => null,
])

@php
    $rawContent = $content ?? $slot;
    $content = trim(is_string($rawContent) ? $rawContent : (string) $rawContent);
    $isJson = \App\Support\JsonSyntaxHighlight::isJson($content);

    if ($isJson) {
        $decoded = json_decode($content, true);
        $content = (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $viewerClass = 's-json-viewer'.($compact ? ' s-json-viewer-compact' : '');
@endphp

<div {{ $attributes->merge(['class' => $viewerClass]) }}>
    @if ($copy && $content !== '' && $content !== '(empty body)')
        <div class="s-json-viewer-toolbar">
            <x-signals.copy-btn :text="$content" label="Copy JSON" class="s-copy-btn-sm" />
        </div>
    @endif

    <pre class="s-json-viewer-content"><code>@if ($isJson){!! \App\Support\JsonSyntaxHighlight::highlight($content) !!}@else{{ $content }}@endif</code></pre>
</div>
