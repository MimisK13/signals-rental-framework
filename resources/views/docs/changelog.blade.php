<x-layouts.docs pageTitle="Changelog" pageDescription="All notable changes to Signals Rental Framework, ordered by version.">
    {{-- Left sidebar navigation --}}
    <x-slot:sidebar>
        @include('docs.partials.sidebar', ['navigation' => $navigation, 'currentSection' => 'changelog'])
    </x-slot:sidebar>

    {{-- Main content --}}
    <article class="docs-prose">
        <h1>Changelog</h1>
        <p class="docs-lead">All notable changes to Signals, ordered by version.</p>

        @foreach ($entries as $entry)
            <section id="v{{ $entry['version'] }}">
                <h2>
                    {{ $entry['version'] }}
                    @if ($entry['title'])
                        <span class="docs-changelog-title">&mdash; {{ $entry['title'] }}</span>
                    @endif
                </h2>
                <time class="docs-changelog-date" datetime="{{ $entry['date'] }}">
                    {{ \Carbon\Carbon::parse($entry['date'])->format('j F Y') }}
                </time>
                {!! $entry['html'] !!}
            </section>
        @endforeach
    </article>

    {{-- Right sidebar: Versions --}}
    <x-slot:toc>
        @if (count($entries) > 0)
            <div class="docs-toc-title">Versions</div>
            <nav class="docs-toc-nav" x-data="docsToc()">
                @foreach ($entries as $entry)
                    <a href="#v{{ $entry['version'] }}"
                       class="docs-toc-link"
                       :class="{ 'active': activeId === 'v{{ $entry['version'] }}' }">
                        {{ $entry['version'] }}
                    </a>
                @endforeach
            </nav>
        @endif
    </x-slot:toc>
</x-layouts.docs>
