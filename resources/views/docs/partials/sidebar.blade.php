@php
    $sidebarCurrentSection = $currentSection ?? '';
    $sidebarCurrentSubSection = $currentSubSection ?? '';
    $sidebarCurrentPage = $currentPage ?? '';
    $sidebarOnChangelog = $sidebarCurrentSection === 'changelog' || request()->routeIs('docs.changelog');
@endphp

@inject('docsService', \App\Services\DocsService::class)

<div
    class="docs-sidebar-nav"
    x-data="docsSidebar(@js([
        'currentSection' => $sidebarCurrentSection,
        'currentSubSection' => $sidebarCurrentSubSection,
        'currentPage' => $sidebarCurrentPage,
        'onChangelog' => $sidebarOnChangelog,
    ]))"
>
    @foreach ($navigation['sections'] as $section)
        @php
            $isCurrentSection = ! $sidebarOnChangelog && $sidebarCurrentSection === $section['slug'];
        @endphp
        <div
            class="docs-nav-section {{ $isCurrentSection ? 'docs-nav-section--current' : '' }}"
            data-section-slug="{{ $section['slug'] }}"
        >
            <button
                type="button"
                class="docs-nav-section-toggle"
                @click="toggleSection(@js($section['slug']))"
                :aria-expanded="isSectionOpen(@js($section['slug'])) ? 'true' : 'false'"
            >
                <span>{{ $section['title'] }}</span>
                <svg class="docs-nav-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </button>

            <div class="docs-nav-section-panel" x-show="isSectionOpen(@js($section['slug']))" x-cloak>
                @foreach ($section['pages'] ?? [] as $page)
                    @php
                        $isActive = $isCurrentSection && $sidebarCurrentSubSection === '' && $sidebarCurrentPage === $page['slug'];
                        $pageUrl = $docsService->pageUrl($page, $section['slug']);
                    @endphp
                    <a
                        href="{{ $pageUrl }}"
                        class="docs-nav-item {{ $isActive ? 'active' : '' }}"
                    >{{ $page['title'] }}</a>
                @endforeach

                @foreach ($section['subSections'] ?? [] as $subSection)
                    @php
                        $subSectionSlug = $subSection['slug'];
                        $isCurrentSubSection = $isCurrentSection && $sidebarCurrentSubSection === $subSectionSlug;
                    @endphp
                    <div class="docs-nav-subsection docs-nav-nest-1" data-subsection-slug="{{ $subSectionSlug }}">
                        <button
                            type="button"
                            class="docs-nav-subsection-toggle"
                            @click="toggleSubSection(@js($subSectionSlug))"
                            :aria-expanded="isSubSectionOpen(@js($subSectionSlug)) ? 'true' : 'false'"
                        >
                            <span>{{ $subSection['label'] }}</span>
                            <svg class="docs-nav-chevron docs-nav-chevron--nested" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>

                        <div class="docs-nav-subsection-panel" x-show="isSubSectionOpen(@js($subSectionSlug))" x-cloak>
                            @foreach ($subSection['groups'] ?? [] as $group)
                                <div class="docs-nav-subgroup docs-nav-nest-2" data-group-label="{{ $group['label'] }}">
                                    <div class="docs-nav-group-label">{{ $group['label'] }}</div>
                                    @foreach ($group['pages'] as $page)
                                        @php
                                            $isActive = $isCurrentSubSection && $sidebarCurrentPage === $page['slug'];
                                            $pageUrl = $docsService->pageUrl($page, $section['slug']);
                                        @endphp
                                        <a
                                            href="{{ $pageUrl }}"
                                            class="docs-nav-item {{ $isActive ? 'active' : '' }}"
                                        >{{ $page['title'] }}</a>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <a
        href="{{ route('docs.changelog') }}"
        class="docs-nav-changelog {{ $sidebarOnChangelog ? 'active' : '' }}"
    >Changelog</a>
</div>
