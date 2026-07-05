<x-layouts.docs
    pageTitle="Overview"
    pageDescription="Browse Signals Rental Framework documentation — installation, platform guides, API reference, and developer resources."
    :landing="true"
>
    <x-slot:sidebar>
        @include('docs.partials.sidebar', ['navigation' => $navigation])
    </x-slot:sidebar>

    <div class="docs-landing">
        <section class="docs-landing-hero">
            <p class="docs-landing-eyebrow">Signals Rental Framework</p>
            <h1 class="docs-landing-title">Documentation</h1>
            <p class="docs-landing-tagline">Open-source rental management for equipment hire and event production companies.</p>
        </section>

        <section class="docs-landing-grid" aria-label="Documentation topics">
            @foreach ($landingCards as $card)
                <article class="docs-landing-card">
                    <div class="docs-landing-card-icon">
                        @include('docs.partials.section-icon', ['icon' => $card['icon']])
                    </div>
                    <div class="docs-landing-card-body">
                        <p class="docs-landing-card-section">{{ $card['sectionTitle'] }}</p>
                        <h2 class="docs-landing-card-title">{{ $card['title'] }}</h2>
                        <p class="docs-landing-card-description">{{ $card['description'] }}</p>

                        @if ($card['pages'] !== [])
                            <ul class="docs-landing-card-links">
                                @foreach ($card['pages'] as $page)
                                    <li>
                                        <a href="{{ $page['url'] }}">{{ $page['title'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <a href="{{ $card['viewAllUrl'] }}" class="docs-landing-card-more">View all &rarr;</a>
                    </div>
                </article>
            @endforeach

            @if ($latestChangelogVersion)
                <article class="docs-landing-card docs-landing-card-changelog">
                    <div class="docs-landing-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </div>
                    <div class="docs-landing-card-body">
                        <p class="docs-landing-card-section">Resources</p>
                        <h2 class="docs-landing-card-title">Changelog</h2>
                        <p class="docs-landing-card-description">All notable changes to Signals Rental Framework, ordered by version.</p>
                        <p class="docs-landing-card-version">Latest release: <strong>v{{ $latestChangelogVersion }}</strong></p>
                        <a href="{{ route('docs.changelog') }}" class="docs-landing-card-more">View changelog &rarr;</a>
                    </div>
                </article>
            @endif
        </section>
    </div>

    <x-slot:toc></x-slot:toc>
</x-layouts.docs>
