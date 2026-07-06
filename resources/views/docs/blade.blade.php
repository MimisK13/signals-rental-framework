<x-layouts.docs :pageTitle="$title" :pageDescription="$description ?? ''" :currentSection="$sectionTitle">
    {{-- Left sidebar navigation --}}
    <x-slot:sidebar>
        @include('docs.partials.sidebar', ['navigation' => $navigation, 'currentSection' => $currentSection, 'currentSubSection' => $currentSubSection ?? '', 'currentPage' => $currentPage])
    </x-slot:sidebar>

    {{-- Main content: rendered from blade view --}}
    @include($bladeView)

    {{-- Previous / Next navigation --}}
    <nav class="docs-pagination">
        @if ($prev)
            <a href="{{ route('docs.show', [$prev['section'], $prev['slug']]) }}" class="docs-pagination-link docs-pagination-prev">
                <span class="docs-pagination-label">&larr; Previous</span>
                <span class="docs-pagination-title">{{ $prev['title'] }}</span>
            </a>
        @else
            <div></div>
        @endif
        @if ($next)
            <a href="{{ route('docs.show', [$next['section'], $next['slug']]) }}" class="docs-pagination-link docs-pagination-next">
                <span class="docs-pagination-label">Next &rarr;</span>
                <span class="docs-pagination-title">{{ $next['title'] }}</span>
            </a>
        @endif
    </nav>

    {{-- Right sidebar: empty for blade pages --}}
    <x-slot:toc></x-slot:toc>
</x-layouts.docs>
