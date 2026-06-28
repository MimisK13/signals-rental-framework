{{--
    Shared sticky left column for availability grids.

    Pass `$label` for plain text, `$url` + `$label` for a link, or `$labelHtml` for rich markup.
--}}
@php
    $muted = $muted ?? false;
    $labelClass = $muted
        ? 'text-[12px] font-medium text-[var(--text-muted)]'
        : '';
    $labelTitle = $labelTitle ?? ($label ?? null);
@endphp

<td class="sticky left-0 z-10 bg-[var(--card-bg)] s-avail-group-product-col overflow-hidden {{ $labelClass }}">
    @if(isset($labelHtml))
        {!! $labelHtml !!}
    @else
        <div class="s-avail-group-row-label">
            @if(! empty($url))
                <a href="{{ $url }}"
                   @if($navigate ?? true) wire:navigate @endif
                   class="s-cell-link s-avail-group-row-label-text text-[var(--blue)] hover:underline"
                   @if($labelTitle) title="{{ $labelTitle }}" @endif>{{ $label }}</a>
            @else
                <span class="s-cell-link s-avail-group-row-label-text text-[var(--blue)]"
                      @if($labelTitle) title="{{ $labelTitle }}" @endif>{{ $label ?? '' }}</span>
            @endif
        </div>
    @endif
</td>
