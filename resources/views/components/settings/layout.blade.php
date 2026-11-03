@props(['heading' => '', 'subheading' => ''])

<div class="s-admin-main min-w-0">
    <div class="s-admin-section-header">
        <h1 class="s-admin-section-title">{{ $heading }}</h1>
        @if($subheading)
            <p class="s-admin-section-desc">{{ $subheading }}</p>
        @endif
    </div>

    <div class="w-full min-w-0 max-w-lg">
        {{ $slot }}
    </div>
</div>
