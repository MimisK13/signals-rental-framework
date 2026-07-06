<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * @phpstan-type DocsPage array{title: string, slug: string, description?: string, type?: string, view?: string, urlSection?: string}
 * @phpstan-type DocsPageNormalized array{title: string, slug: string, urlSection: string, description?: string, type?: string, view?: string}
 * @phpstan-type DocsGroup array{label: string, pages: array<int, DocsPageNormalized>}
 * @phpstan-type DocsSubSection array{label: string, slug: string, groups: array<int, DocsGroup>}
 * @phpstan-type DocsSection array{
 *     title: string,
 *     slug: string,
 *     icon?: string,
 *     description?: string,
 *     pages?: array<int, DocsPageNormalized>,
 *     groups?: array<int, DocsGroup>,
 *     subSections?: array<int, DocsSubSection>
 * }
 * @phpstan-type DocsNavigation array{sections: array<int, DocsSection>}
 * @phpstan-type DocsNavEntry array{
 *     section: DocsSection,
 *     sectionSlug: string,
 *     subSectionSlug: ?string,
 *     group: ?string,
 *     page: DocsPageNormalized
 * }
 */
class DocsService
{
    private const NAVIGATION_CACHE_KEY = 'docs:navigation:v2';

    private const SEARCH_INDEX_CACHE_KEY = 'docs:search-index:v2';

    private ?MarkdownConverter $converter = null;

    /**
     * @param  string|null  $docsPath  Filesystem root for documentation files. Defaults to base_path('docs').
     */
    public function __construct(private readonly ?string $docsPath = null) {}

    /**
     * Resolve the documentation root directory.
     */
    private function docsRoot(): string
    {
        return $this->docsPath ?? base_path('docs');
    }

    /**
     * Load and decode the documentation navigation manifest with groups resolved.
     *
     * Grouped sections include both `groups` and a flattened `pages` array for backwards compatibility.
     *
     * @return DocsNavigation
     */
    public function getNavigation(): array
    {
        if (App::environment('local')) {
            return $this->resolveNavigation($this->loadManifest());
        }

        return Cache::remember(self::NAVIGATION_CACHE_KEY, 3600, fn (): array => $this->resolveNavigation($this->loadManifest()));
    }

    /**
     * Parse a markdown file and return rendered content with metadata.
     *
     * @return array{title: string, description: ?string, html: ?string, toc: array<int, array{level: int, id: string, text: string}>, type?: string, view?: string}|null
     */
    public function getPage(string $section, string $page): ?array
    {
        $entry = $this->getManifestPageEntry($section, $page);

        if ($entry !== null && ($entry['type'] ?? null) === 'blade') {
            return [
                'title' => $entry['title'],
                'description' => $entry['description'] ?? null,
                'html' => null,
                'toc' => [],
                'type' => 'blade',
                'view' => $entry['view'],
            ];
        }

        $filePath = $this->resolveFilePath($section, $page);

        if ($filePath === null) {
            return null;
        }

        $markdown = file_get_contents($filePath);

        if ($markdown === false) {
            return null;
        }

        $result = $this->getConverter()->convert($markdown);

        $frontMatter = [];
        if ($result instanceof RenderedContentWithFrontMatter) {
            $frontMatter = $result->getFrontMatter();
        }

        $manifestTitle = $this->getManifestTitle($section, $page);
        $html = (string) $result;

        return [
            'title' => $frontMatter['title'] ?? $manifestTitle ?? $page,
            'description' => $frontMatter['description'] ?? $entry['description'] ?? null,
            'html' => $html,
            'toc' => $this->extractTableOfContents($html),
        ];
    }

    /**
     * Extract h2/h3 headings from rendered HTML for the "On This Page" sidebar.
     *
     * @return array<int, array{level: int, id: string, text: string}>
     */
    public function extractTableOfContents(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $doc = new DOMDocument;
        @$doc->loadHTML(
            '<meta charset="utf-8">'.$html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        $xpath = new DOMXPath($doc);
        $headings = $xpath->query('//h2|//h3');

        if ($headings === false) {
            return [];
        }

        $toc = [];
        foreach ($headings as $heading) {
            if (! $heading instanceof \DOMElement) {
                continue;
            }

            $id = $heading->getAttribute('id');
            if ($id === '') {
                continue;
            }

            $toc[] = [
                'level' => (int) substr($heading->nodeName, 1),
                'id' => $id,
                'text' => rtrim(trim($heading->textContent), '#'),
            ];
        }

        return $toc;
    }

    /**
     * Load all changelog entries, sorted newest-first by version.
     *
     * @return array<int, array{version: string, date: string, title: ?string, html: string}>
     */
    public function getChangelog(): array
    {
        if (App::environment('local')) {
            return $this->loadChangelogEntries();
        }

        return Cache::remember('docs:changelog', 3600, fn (): array => $this->loadChangelogEntries());
    }

    /**
     * Check whether any changelog entries exist.
     */
    public function changelogExists(): bool
    {
        $dir = $this->docsRoot().'/changelog';

        if (! is_dir($dir)) {
            return false;
        }

        return count(glob($dir.'/*.md') ?: []) > 0;
    }

    /**
     * Build a search index with metadata, heading text, and plain-text body content.
     *
     * @return array<int, array{title: string, section: string, sectionSlug: string, group: ?string, slug: string, url: string, description: string, headings: array<int, string>, content: string}>
     */
    public function getSearchIndex(): array
    {
        if (App::environment('local')) {
            return $this->buildSearchIndex();
        }

        return Cache::remember(self::SEARCH_INDEX_CACHE_KEY, 3600, fn (): array => $this->buildSearchIndex());
    }

    /**
     * @return array<int, array{urlSection: string, slug: string}>
     */
    public function getAllPageRouteKeys(): array
    {
        $routes = [];

        foreach ($this->iterateResolvedNavigation($this->getNavigation()) as $entry) {
            $routes[] = [
                'urlSection' => $entry['page']['urlSection'],
                'slug' => $entry['page']['slug'],
            ];
        }

        return $routes;
    }

    /**
     * @return array<int, string>
     */
    public function getSitemapUrls(): array
    {
        $urls = [];

        foreach ($this->getNavigation()['sections'] as $section) {
            if (isset($section['pages'])) {
                foreach ($section['pages'] as $page) {
                    $urls[] = $this->pageUrl($page, (string) $section['slug']);
                }
            }

            if (isset($section['subSections'])) {
                foreach ($section['subSections'] as $subSection) {
                    foreach ($subSection['groups'] as $group) {
                        foreach ($group['pages'] as $page) {
                            $urls[] = $this->pageUrl($page, (string) $section['slug']);
                        }
                    }
                }
            }
        }

        if ($this->changelogExists()) {
            $urls[] = route('docs.changelog');
        }

        return $urls;
    }

    /**
     * Resolve display navigation for a route URL section and page slug.
     *
     * @return array{sectionSlug: string, sectionTitle: string, subSectionSlug: ?string, group: ?string}|null
     */
    public function resolveDisplayContext(string $urlSection, string $pageSlug): ?array
    {
        foreach ($this->iterateResolvedNavigation($this->getNavigation()) as $entry) {
            if ($entry['page']['urlSection'] === $urlSection
                && $entry['page']['slug'] === $pageSlug) {
                return [
                    'sectionSlug' => $entry['sectionSlug'],
                    'sectionTitle' => (string) $entry['section']['title'],
                    'subSectionSlug' => $entry['subSectionSlug'],
                    'group' => $entry['group'],
                ];
            }
        }

        return null;
    }

    /**
     * Build the public docs URL for a manifest page entry.
     *
     * @param  array{slug: string, urlSection?: string}  $page
     */
    public function pageUrl(array $page, string $displaySectionSlug): string
    {
        $urlSection = $page['urlSection'] ?? $displaySectionSlug;

        return route('docs.show', [
            $urlSection,
            $page['slug'],
        ]);
    }

    /**
     * Build landing-page cards from display sections and API Reference groups.
     *
     * @return array<int, array{title: string, description: string, icon: string, sectionSlug: string, sectionTitle: string, pages: array<int, array{title: string, slug: string, url: string}>, viewAllUrl: string}>
     */
    public function getLandingCards(): array
    {
        $cards = [];

        foreach ($this->getNavigation()['sections'] as $section) {
            if (isset($section['pages']) && $section['pages'] !== []) {
                $cards[] = $this->buildLandingCard(
                    title: (string) $section['title'],
                    description: (string) ($section['description'] ?? ''),
                    icon: (string) ($section['icon'] ?? 'file'),
                    sectionSlug: (string) $section['slug'],
                    sectionTitle: (string) $section['title'],
                    pages: $section['pages'],
                );
            }

            if (isset($section['subSections'])) {
                foreach ($section['subSections'] as $subSection) {
                    foreach ($subSection['groups'] as $group) {
                        $cards[] = $this->buildLandingCard(
                            title: (string) $group['label'],
                            description: (string) ($section['description'] ?? ''),
                            icon: (string) ($section['icon'] ?? 'file'),
                            sectionSlug: (string) $section['slug'],
                            sectionTitle: (string) $section['title'],
                            pages: $group['pages'],
                        );
                    }
                }
            }
        }

        return $cards;
    }

    /**
     * Resolve the newest changelog version from changelog markdown filenames.
     */
    public function getLatestChangelogVersion(): ?string
    {
        $dir = $this->docsRoot().'/changelog';

        if (! is_dir($dir)) {
            return null;
        }

        $versions = [];

        foreach (glob($dir.'/*.md') ?: [] as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);

            if ($version !== '') {
                $versions[] = $version;
            }
        }

        if ($versions === []) {
            return null;
        }

        usort($versions, fn (string $a, string $b): int => version_compare($b, $a));

        return $versions[0];
    }

    /**
     * Check whether a given section/page combination exists.
     */
    public function pageExists(string $section, string $page): bool
    {
        if (! $this->isInManifest($section, $page)) {
            return false;
        }

        $entry = $this->getManifestPageEntry($section, $page);

        if ($entry !== null && ($entry['type'] ?? null) === 'blade') {
            return true;
        }

        return $this->resolveFilePath($section, $page) !== null;
    }

    /**
     * Resolve the previous and next pages for sequential navigation.
     *
     * @return array{prev: ?array{title: string, section: string, slug: string}, next: ?array{title: string, section: string, slug: string}}
     */
    public function getAdjacentPages(string $section, string $page): array
    {
        $flatPages = $this->flattenNavigation();
        $currentIndex = null;

        foreach ($flatPages as $index => $entry) {
            if ($entry['section'] === $section && $entry['slug'] === $page) {
                $currentIndex = $index;
                break;
            }
        }

        return [
            'prev' => $currentIndex !== null && $currentIndex > 0
                ? $flatPages[$currentIndex - 1]
                : null,
            'next' => $currentIndex !== null && $currentIndex < count($flatPages) - 1
                ? $flatPages[$currentIndex + 1]
                : null,
        ];
    }

    /**
     * Build the CommonMark converter with all required extensions.
     */
    private function getConverter(): MarkdownConverter
    {
        if ($this->converter !== null) {
            return $this->converter;
        }

        $config = [
            'heading_permalink' => [
                'html_class' => 'docs-heading-anchor',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'after',
                'symbol' => '#',
                'min_heading_level' => 2,
                'max_heading_level' => 3,
                'title' => 'Permalink',
                'aria_hidden' => true,
                'apply_id_to_heading' => true,
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);
        $environment->addExtension(new FrontMatterExtension);

        $this->converter = new MarkdownConverter($environment);

        return $this->converter;
    }

    /**
     * Resolve the filesystem path for a docs page, with path traversal protection.
     */
    private function resolveFilePath(string $section, string $page): ?string
    {
        $docsBase = $this->docsRoot();
        $expectedPath = $docsBase.DIRECTORY_SEPARATOR.$section.DIRECTORY_SEPARATOR.$page.'.md';

        $realPath = realpath($expectedPath);

        if ($realPath === false) {
            return null;
        }

        $realDocsBase = realpath($docsBase);

        if ($realDocsBase === false || ! str_starts_with($realPath, $realDocsBase.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }

    /**
     * Load and decode the documentation.json manifest file.
     *
     * @return array{sections: array<int, array<string, mixed>>}
     */
    private function loadManifest(): array
    {
        $path = $this->docsRoot().'/documentation.json';

        if (! file_exists($path)) {
            return ['sections' => []];
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return ['sections' => []];
        }

        /** @var array{sections: array<int, array<string, mixed>>}|null $decoded */
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return ['sections' => []];
        }

        return $decoded;
    }

    /**
     * Resolve grouped sections into a navigation structure with flattened pages.
     *
     * @param  array{sections: array<int, array<string, mixed>>}  $manifest
     * @return DocsNavigation
     */
    private function resolveNavigation(array $manifest): array
    {
        $sections = [];

        foreach ($manifest['sections'] as $section) {
            $displaySlug = (string) $section['slug'];

            if (isset($section['pages']) && is_array($section['pages'])) {
                $section['pages'] = array_map(
                    fn (array $page): array => $this->normalizePage($page, $displaySlug),
                    $section['pages'],
                );
            }

            if (isset($section['groups']) && is_array($section['groups'])) {
                $flattenedPages = [];

                foreach ($section['groups'] as $groupIndex => $group) {
                    $normalizedPages = array_map(
                        fn (array $page): array => $this->normalizePage($page, $displaySlug),
                        $group['pages'] ?? [],
                    );

                    $section['groups'][$groupIndex]['pages'] = $normalizedPages;
                    array_push($flattenedPages, ...$normalizedPages);
                }

                $section['pages'] = $flattenedPages;
            }

            if (isset($section['subSections']) && is_array($section['subSections'])) {
                foreach ($section['subSections'] as $subSectionIndex => $subSection) {
                    $section['subSections'][$subSectionIndex]['slug'] = isset($subSection['slug'])
                        ? (string) $subSection['slug']
                        : Str::slug((string) ($subSection['label'] ?? 'subsection'));

                    foreach ($subSection['groups'] ?? [] as $groupIndex => $group) {
                        $section['subSections'][$subSectionIndex]['groups'][$groupIndex]['pages'] = array_map(
                            fn (array $page): array => $this->normalizePage($page, $displaySlug),
                            $group['pages'] ?? [],
                        );
                    }
                }
            }

            $sections[] = $section;
        }

        return ['sections' => $sections];
    }

    /**
     * @param  array{title: string, slug: string, urlSection?: string, description?: string, type?: string, view?: string}  $page
     * @return DocsPageNormalized
     */
    private function normalizePage(array $page, string $displaySectionSlug): array
    {
        if (! isset($page['urlSection'])) {
            $page['urlSection'] = $displaySectionSlug;
        }

        return $page;
    }

    /**
     * Check whether a section/page combination exists in the manifest.
     */
    private function isInManifest(string $section, string $page): bool
    {
        return $this->getManifestPageEntry($section, $page) !== null;
    }

    /**
     * Get the display title for a page from the manifest.
     */
    private function getManifestTitle(string $section, string $page): ?string
    {
        $entry = $this->getManifestPageEntry($section, $page);

        return $entry['title'] ?? null;
    }

    /**
     * Retrieve the full page entry from the manifest, including type and view keys.
     *
     * @return DocsPageNormalized|null
     */
    private function getManifestPageEntry(string $section, string $page): ?array
    {
        foreach ($this->iterateResolvedNavigation($this->getNavigation()) as $entry) {
            $urlSection = $entry['page']['urlSection'];

            if ($urlSection === $section && $entry['page']['slug'] === $page) {
                return $entry['page'];
            }
        }

        return null;
    }

    /**
     * Iterate every page declared in the navigation manifest.
     *
     * @return \Generator<int, DocsNavEntry>
     */
    private function iterateManifestPages(): \Generator
    {
        yield from $this->iterateResolvedNavigation($this->getNavigation());
    }

    /**
     * Iterate pages from an already-resolved navigation structure.
     *
     * @param  DocsNavigation  $navigation
     * @return \Generator<int, DocsNavEntry>
     */
    private function iterateResolvedNavigation(array $navigation): \Generator
    {
        foreach ($navigation['sections'] as $section) {
            if (isset($section['groups'])) {
                foreach ($section['groups'] as $group) {
                    foreach ($group['pages'] as $page) {
                        yield [
                            'section' => $section,
                            'sectionSlug' => $section['slug'],
                            'subSectionSlug' => null,
                            'group' => $group['label'],
                            'page' => $page,
                        ];
                    }
                }
            } elseif (isset($section['pages'])) {
                foreach ($section['pages'] as $page) {
                    yield [
                        'section' => $section,
                        'sectionSlug' => $section['slug'],
                        'subSectionSlug' => null,
                        'group' => null,
                        'page' => $page,
                    ];
                }
            }

            if (isset($section['subSections'])) {
                foreach ($section['subSections'] as $subSection) {
                    foreach ($subSection['groups'] as $group) {
                        foreach ($group['pages'] as $page) {
                            yield [
                                'section' => $section,
                                'sectionSlug' => $section['slug'],
                                'subSectionSlug' => $subSection['slug'],
                                'group' => $group['label'],
                                'page' => $page,
                            ];
                        }
                    }
                }
            }
        }
    }

    /**
     * Build the search index from manifest metadata, markdown headings, and body text.
     *
     * @return array<int, array{title: string, section: string, sectionSlug: string, group: ?string, slug: string, url: string, description: string, headings: array<int, string>, content: string}>
     */
    private function buildSearchIndex(): array
    {
        $index = [];

        foreach ($this->iterateManifestPages() as $entry) {
            $section = $entry['section'];
            $page = $entry['page'];
            $description = $page['description'] ?? '';

            if (($page['type'] ?? null) === 'blade') {
                $index[] = [
                    'title' => $page['title'],
                    'section' => $section['title'],
                    'sectionSlug' => $section['slug'],
                    'group' => $entry['group'],
                    'slug' => $page['slug'],
                    'url' => route('docs.show', [$page['urlSection'], $page['slug']]),
                    'description' => $description,
                    'headings' => [],
                    'content' => '',
                ];

                continue;
            }

            $urlSection = $page['urlSection'];
            $filePath = $this->resolveFilePath($urlSection, $page['slug']);

            if ($filePath === null) {
                continue;
            }

            $pageContent = $this->getPage($urlSection, $page['slug']);

            if ($pageContent === null || ! is_string($pageContent['html'])) {
                continue;
            }

            $headings = array_column($pageContent['toc'], 'text');

            $index[] = [
                'title' => $pageContent['title'],
                'section' => $section['title'],
                'sectionSlug' => $section['slug'],
                'group' => $entry['group'],
                'slug' => $page['slug'],
                'url' => route('docs.show', [$urlSection, $page['slug']]),
                'description' => $description,
                'headings' => $headings,
                'content' => $this->plainTextFromHtml($pageContent['html']),
            ];
        }

        foreach ($this->loadChangelogEntries() as $changelogEntry) {
            $index[] = [
                'title' => $changelogEntry['version'],
                'section' => 'Changelog',
                'sectionSlug' => 'changelog',
                'group' => null,
                'slug' => 'changelog-'.$changelogEntry['version'],
                'url' => route('docs.changelog').'#v'.$changelogEntry['version'],
                'description' => $changelogEntry['title'] ?? 'Release notes for version '.$changelogEntry['version'],
                'headings' => array_column($this->extractTableOfContents($changelogEntry['html']), 'text'),
                'content' => $this->plainTextFromHtml($changelogEntry['html']),
            ];
        }

        return $index;
    }

    /**
     * Strip rendered HTML down to searchable plain text.
     */
    private function plainTextFromHtml(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    }

    /**
     * Load and parse all changelog markdown files.
     *
     * @return array<int, array{version: string, date: string, title: ?string, html: string}>
     */
    private function loadChangelogEntries(): array
    {
        $dir = $this->docsRoot().'/changelog';

        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir.'/*.md') ?: [];
        $entries = [];

        foreach ($files as $file) {
            $markdown = file_get_contents($file);
            if ($markdown === false) {
                continue;
            }

            $result = $this->getConverter()->convert($markdown);

            $frontMatter = [];
            if ($result instanceof RenderedContentWithFrontMatter) {
                $frontMatter = $result->getFrontMatter();
            }

            if (! isset($frontMatter['version'], $frontMatter['date'])) {
                continue;
            }

            $date = $frontMatter['date'];
            if (is_int($date)) {
                $date = date('Y-m-d', $date);
            }

            $entries[] = [
                'version' => (string) $frontMatter['version'],
                'date' => (string) $date,
                'title' => isset($frontMatter['title']) ? (string) $frontMatter['title'] : null,
                'html' => (string) $result,
            ];
        }

        usort($entries, fn (array $a, array $b): int => version_compare($b['version'], $a['version']));

        return $entries;
    }

    /**
     * @param  array<int, array{title: string, slug: string, description?: string, type?: string, view?: string}>  $pages
     * @return array{title: string, description: string, icon: string, sectionSlug: string, sectionTitle: string, pages: array<int, array{title: string, slug: string, url: string}>, viewAllUrl: string}
     */
    private function buildLandingCard(
        string $title,
        string $description,
        string $icon,
        string $sectionSlug,
        string $sectionTitle,
        array $pages,
    ): array {
        $featuredPages = array_slice($pages, 0, 4);
        $firstPage = $pages[0] ?? null;

        return [
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'sectionSlug' => $sectionSlug,
            'sectionTitle' => $sectionTitle,
            'pages' => array_map(
                fn (array $page): array => [
                    'title' => $page['title'],
                    'slug' => $page['slug'],
                    'url' => $this->pageUrl($page, $sectionSlug),
                ],
                $featuredPages,
            ),
            'viewAllUrl' => $firstPage !== null
                ? $this->pageUrl($firstPage, $sectionSlug)
                : route('docs.index'),
        ];
    }

    /**
     * Flatten the navigation into a sequential list of all pages.
     *
     * @return array<int, array{title: string, section: string, slug: string}>
     */
    private function flattenNavigation(): array
    {
        $flat = [];

        foreach ($this->iterateManifestPages() as $entry) {
            $flat[] = [
                'title' => $entry['page']['title'],
                'section' => $entry['page']['urlSection'],
                'slug' => $entry['page']['slug'],
            ];
        }

        return $flat;
    }
}
