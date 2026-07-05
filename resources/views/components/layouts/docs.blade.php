@props(['pageTitle' => '', 'pageDescription' => '', 'canonicalUrl' => '', 'currentSection' => '', 'landing' => false])

@inject('docsService', \App\Services\DocsService::class)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Signals Rental Framework - Documentation' . ($pageTitle ? ' | ' . $pageTitle : '')])

        {{-- Docs always use the default Signals brand, not user-configured colours --}}
        <style>:root { --brand-primary: #1e3a5f; --brand-accent: #3b82f6; }</style>

        @if ($pageDescription)
            <meta name="description" content="{{ $pageDescription }}">
        @endif
        <link rel="canonical" href="{{ $canonicalUrl ?: request()->url() }}">

        {{-- Open Graph --}}
        <meta property="og:type" content="article">
        <meta property="og:site_name" content="Signals Rental Framework">
        <meta property="og:title" content="{{ $pageTitle ?: 'Documentation' }}">
        @if ($pageDescription)
            <meta property="og:description" content="{{ $pageDescription }}">
        @endif
        <meta property="og:url" content="{{ $canonicalUrl ?: request()->url() }}">

        {{-- Twitter Card --}}
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $pageTitle ?: 'Documentation' }}">
        @if ($pageDescription)
            <meta name="twitter:description" content="{{ $pageDescription }}">
        @endif

        {{-- JSON-LD: BreadcrumbList (show pages) --}}
        @if ($currentSection)
            <script type="application/ld+json">
            {
                "@@context": "https://schema.org",
                "@@type": "BreadcrumbList",
                "itemListElement": [
                    {"@@type": "ListItem", "position": 1, "name": "Documentation", "item": "{{ route('docs.index') }}"},
                    {"@@type": "ListItem", "position": 2, "name": "{{ $currentSection }}", "item": "{{ request()->url() }}"},
                    {"@@type": "ListItem", "position": 3, "name": "{{ $pageTitle }}"}
                ]
            }
            </script>
        @endif

        {{-- JSON-LD: WebSite --}}
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebSite",
            "name": "Signals Rental Framework Documentation",
            "url": "{{ route('docs.index') }}"
        }
        </script>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github-dark.min.css">
        <script src="https://cdn.jsdelivr.net/npm/turndown@7.2.0/dist/turndown.min.js" defer></script>
    </head>
    <body class="min-h-screen bg-[var(--content-bg)] text-[13px] leading-normal text-[var(--text-primary)] antialiased" x-data>

        {{-- Docs header --}}
        <header class="docs-header">
            <a href="{{ route('home') }}" class="docs-header-brand">
                <span class="footer-mark" style="font-size: 10px; padding: 2px 8px;">
                    SIGNALS<span class="footer-mark-accent"></span>
                </span>
            </a>
            <a href="{{ route('docs.index') }}" class="docs-header-title">Documentation</a>
            <button type="button" class="docs-search-trigger" @click="$dispatch('open-docs-search')" aria-label="Search documentation">
                <svg class="docs-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                <span>Search docs...</span>
                <kbd class="docs-search-kbd">⌘K</kbd>
            </button>
            <div class="docs-header-actions">
                <a href="/docs/api" target="_blank" rel="noopener noreferrer" class="docs-header-api-link">API Reference</a>
                <a href="https://github.com/signals-rental/framework" target="_blank" rel="noopener noreferrer" class="header-icon-btn" aria-label="GitHub" title="GitHub">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12Z"/></svg>
                </a>
                <button x-data x-on:click="$flux.dark = ! $flux.dark" class="header-icon-btn" aria-label="Toggle dark mode">
                    <svg x-show="! $flux.dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" /></svg>
                    <svg x-show="$flux.dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>
                </button>
            </div>
        </header>

        {{-- Mobile bar: nav toggle + actions --}}
        <div class="docs-mobile-bar" x-data="{ navOpen: false }">
            <div class="docs-mobile-bar-row">
                <button @click="navOpen = !navOpen" class="docs-mobile-toggle">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    Navigation
                </button>
                <div class="docs-mobile-actions">
                    <button @click="$dispatch('open-docs-search')" class="header-icon-btn" aria-label="Search documentation">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    </button>
                    <a href="https://github.com/signals-rental/framework" target="_blank" rel="noopener noreferrer" class="header-icon-btn" aria-label="GitHub" title="GitHub">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12Z"/></svg>
                    </a>
                    <button x-data x-on:click="$flux.dark = ! $flux.dark" class="header-icon-btn" aria-label="Toggle dark mode">
                        <svg x-show="! $flux.dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" /></svg>
                        <svg x-show="$flux.dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>
                    </button>
                </div>
            </div>
            <nav class="docs-mobile-nav" x-show="navOpen" x-cloak @click.outside="navOpen = false" x-transition>
                {{ $sidebar }}
            </nav>
        </div>

        {{-- Three-column layout --}}
        <div @class(['docs-layout', 'docs-layout-landing' => $landing])>
            {{-- Left sidebar --}}
            <aside class="docs-sidebar">
                <nav class="docs-nav">
                    {{ $sidebar }}
                </nav>
            </aside>

            {{-- Main content --}}
            <main class="docs-content">
                {{ $slot }}
            </main>

            {{-- Right sidebar: On This Page + Promo --}}
            @unless($landing)
            <aside class="docs-toc">
                {{ $toc ?? '' }}

                {{-- Promo placeholder --}}
                <div class="docs-promo">
                    <div class="docs-promo-image">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 168 96" fill="none">
                            <rect width="168" height="96" rx="2" fill="var(--table-header-bg)"/>
                            <rect x="24" y="16" width="120" height="8" rx="2" fill="var(--card-border)"/>
                            <rect x="34" y="32" width="100" height="6" rx="2" fill="var(--card-border)" opacity="0.6"/>
                            <rect x="44" y="44" width="80" height="6" rx="2" fill="var(--card-border)" opacity="0.4"/>
                            <circle cx="84" cy="66" r="8" fill="var(--green)" opacity="0.3"/>
                            <path d="M80 66l3 3 5-5" stroke="var(--green)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="54" y="80" width="60" height="6" rx="2" fill="var(--card-border)" opacity="0.3"/>
                        </svg>
                    </div>
                    <div class="docs-promo-badge">Signals Cloud</div>
                    <div class="docs-promo-title">Managed hosting for Signals</div>
                    <p class="docs-promo-text">Deploy in seconds. We handle servers, backups, and updates so you can focus on your rental business.</p>
                    <a href="https://signals.rent" class="docs-promo-cta">Learn more &rarr;</a>
                </div>
            </aside>
            @endunless
        </div>

        <div
            class="docs-search-modal-root"
            x-data="docsSearchModal()"
            x-cloak
            @open-docs-search.window="openModal()"
            @keydown.escape.window="close()"
        >
            <div class="docs-search-modal-backdrop" x-show="open" x-transition.opacity @click.self="close()"></div>
            <div
                class="docs-search-modal"
                role="dialog"
                aria-modal="true"
                aria-label="Search documentation"
                x-show="open"
                x-transition
                @keydown.arrow-down.prevent="moveDown()"
                @keydown.arrow-up.prevent="moveUp()"
                @keydown.enter.prevent="go()"
            >
                <div class="docs-search-modal-input-wrap">
                    <svg class="docs-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    <input
                        type="search"
                        class="docs-search-modal-input"
                        placeholder="Search documentation..."
                        x-model="query"
                        x-ref="modalInput"
                        @input="selected = 0"
                    >
                    <kbd class="docs-search-modal-kbd">Esc</kbd>
                </div>

                <div class="docs-search-modal-results" x-show="query.trim().length > 0 && groupedResults.length > 0">
                    <template x-for="group in groupedResults" :key="group.section">
                        <div class="docs-search-modal-group">
                            <div class="docs-search-modal-group-label" x-text="group.section"></div>
                            <template x-for="item in group.items" :key="item.url">
                                <a
                                    :href="item.url"
                                    class="docs-search-modal-result"
                                    :class="{ 'active': item.index === selected }"
                                    @mouseenter="selected = item.index"
                                >
                                    <span class="docs-search-modal-result-title" x-text="item.title"></span>
                                    <span class="docs-search-modal-result-context" x-show="item.context" x-text="item.context"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="docs-search-modal-empty" x-show="query.trim().length > 0 && groupedResults.length === 0">
                    No results found.
                </div>
            </div>
        </div>

        <script id="docs-search-data" type="application/json">{!! json_encode($docsService->getSearchIndex(), JSON_THROW_ON_ERROR) !!}</script>
        @fluxScripts
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"></script>
        <script>
            hljs.highlightAll();

            document.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    window.dispatchEvent(new CustomEvent('open-docs-search'));
                }
            });

            document.addEventListener('alpine:init', () => {
                Alpine.data('copyMarkdown', () => ({
                    copied: false,
                    copy() {
                        const article = document.querySelector('.docs-prose');
                        if (!article) return;

                        const clone = article.cloneNode(true);
                        clone.querySelectorAll('.docs-article-header button, .docs-heading-anchor').forEach(el => el.remove());

                        const turndown = new TurndownService({ headingStyle: 'atx', codeBlockStyle: 'fenced' });
                        turndown.addRule('fencedCodeBlock', {
                            filter: (node) => node.nodeName === 'PRE' && node.querySelector('code'),
                            replacement: (content, node) => {
                                const code = node.querySelector('code');
                                const lang = (code.className.match(/language-(\S+)/) || [])[1] || '';
                                return '\n\n```' + lang + '\n' + code.textContent.trimEnd() + '\n```\n\n';
                            }
                        });

                        const markdown = turndown.turndown(clone.innerHTML);
                        navigator.clipboard.writeText(markdown);
                        this.copied = true;
                        setTimeout(() => { this.copied = false; }, 2000);
                    }
                }));

                Alpine.data('docsSidebar', (config = {}) => ({
                    currentSection: config.currentSection || '',
                    currentSubSection: config.currentSubSection || '',
                    currentPage: config.currentPage || '',
                    onChangelog: config.onChangelog || false,
                    openSections: {},
                    openSubSections: {},

                    init() {
                        this.loadOpenState();
                    },

                    loadOpenState() {
                        try {
                            const stored = JSON.parse(localStorage.getItem('docs-nav-open') || '{}');

                            if (stored && typeof stored === 'object') {
                                this.openSections = stored;
                            }

                            const storedSubSections = JSON.parse(localStorage.getItem('docs-nav-sub-open') || '{}');

                            if (storedSubSections && typeof storedSubSections === 'object') {
                                this.openSubSections = storedSubSections;
                            }
                        } catch (error) {
                            this.openSections = {};
                            this.openSubSections = {};
                        }
                    },

                    persistOpenState() {
                        localStorage.setItem('docs-nav-open', JSON.stringify(this.openSections));
                        localStorage.setItem('docs-nav-sub-open', JSON.stringify(this.openSubSections));
                    },

                    isSectionOpen(slug) {
                        if (Object.prototype.hasOwnProperty.call(this.openSections, slug)) {
                            return this.openSections[slug] === true;
                        }

                        return ! this.onChangelog && slug === this.currentSection;
                    },

                    toggleSection(slug) {
                        this.openSections[slug] = ! this.isSectionOpen(slug);
                        this.persistOpenState();
                    },

                    isSubSectionOpen(slug) {
                        if (Object.prototype.hasOwnProperty.call(this.openSubSections, slug)) {
                            return this.openSubSections[slug] === true;
                        }

                        return slug === this.currentSubSection;
                    },

                    toggleSubSection(slug) {
                        this.openSubSections[slug] = ! this.isSubSectionOpen(slug);
                        this.persistOpenState();
                    },
                }));

                Alpine.data('docsSearchModal', () => ({
                    open: false,
                    query: '',
                    selected: 0,
                    pages: [],

                    init() {
                        this.pages = JSON.parse(document.getElementById('docs-search-data')?.textContent || '[]');
                    },

                    openModal() {
                        this.open = true;
                        this.query = '';
                        this.selected = 0;
                        this.$nextTick(() => {
                            this.$refs.modalInput?.focus();
                        });
                    },

                    close() {
                        if (! this.open) {
                            return;
                        }

                        this.open = false;
                        this.query = '';
                        this.selected = 0;
                    },

                    get groupedResults() {
                        const query = this.query.trim().toLowerCase();

                        if (! query) {
                            return [];
                        }

                        const groups = new Map();
                        let index = 0;

                        for (const page of this.pages) {
                            const titleMatch = page.title.toLowerCase().includes(query);
                            const descriptionMatch = (page.description || '').toLowerCase().includes(query);
                            const matchedHeading = (page.headings || []).find((heading) => {
                                return heading.toLowerCase().includes(query);
                            });
                            const contentMatch = (page.content || '').toLowerCase().includes(query);

                            if (! titleMatch && ! descriptionMatch && ! matchedHeading && ! contentMatch) {
                                continue;
                            }

                            let context = '';

                            if (matchedHeading) {
                                context = matchedHeading;
                            } else if (descriptionMatch && ! titleMatch) {
                                context = page.description;
                            } else if (contentMatch) {
                                context = this.contentSnippet(page.content, query);
                            }

                            if (! groups.has(page.section)) {
                                groups.set(page.section, {
                                    section: page.section,
                                    items: [],
                                });
                            }

                            groups.get(page.section).items.push({
                                ...page,
                                context,
                                index: index++,
                            });
                        }

                        return Array.from(groups.values());
                    },

                    contentSnippet(content, query) {
                        const normalized = String(content || '');
                        const lowerContent = normalized.toLowerCase();
                        const position = lowerContent.indexOf(query);

                        if (position === -1) {
                            return normalized.slice(0, 120);
                        }

                        const start = Math.max(0, position - 40);
                        const end = Math.min(normalized.length, position + query.length + 80);
                        let snippet = normalized.slice(start, end).trim();

                        if (start > 0) {
                            snippet = '…' + snippet;
                        }

                        if (end < normalized.length) {
                            snippet += '…';
                        }

                        return snippet;
                    },

                    get selectableResults() {
                        return this.groupedResults.flatMap((group) => group.items);
                    },

                    moveDown() {
                        const results = this.selectableResults;

                        if (results.length === 0) {
                            return;
                        }

                        this.selected = Math.min(this.selected + 1, results.length - 1);
                    },

                    moveUp() {
                        if (this.selected > 0) {
                            this.selected--;
                        }
                    },

                    go() {
                        const result = this.selectableResults[this.selected];

                        if (result) {
                            window.location.href = result.url;
                        }
                    },
                }));

                Alpine.data('docsToc', () => ({
                    activeId: '',
                    init() {
                        const headings = document.querySelectorAll('.docs-prose h2[id], .docs-prose h3[id]');
                        if (headings.length === 0) return;

                        const observer = new IntersectionObserver(
                            (entries) => {
                                entries.forEach(entry => {
                                    if (entry.isIntersecting) {
                                        this.activeId = entry.target.id;
                                    }
                                });
                            },
                            { rootMargin: '0px 0px -80% 0px', threshold: 0.1 }
                        );
                        headings.forEach(h => observer.observe(h));
                    }
                }));
            });
        </script>
    </body>
</html>
