<?php

use App\Services\DocsService;

test('getNavigation returns display sections with pages or nested api reference', function () {
    $service = app(DocsService::class);
    $nav = $service->getNavigation();

    expect($nav)->toHaveKey('sections')
        ->and($nav['sections'])->toBeArray()->toHaveCount(7)
        ->and($nav['sections'][0])->toHaveKeys(['title', 'slug', 'icon', 'description', 'pages'])
        ->and($nav['sections'][0]['pages'])->toBeArray()->toHaveCount(6)
        ->and($nav['sections'][0]['pages'][0])->toHaveKeys(['title', 'slug', 'description'])
        ->and($nav['sections'][1]['slug'])->toBe('crm')
        ->and($nav['sections'][1]['pages'])->toHaveCount(3)
        ->and($nav['sections'][6]['slug'])->toBe('development')
        ->and($nav['sections'][6]['pages'])->toHaveCount(4)
        ->and($nav['sections'][6])->toHaveKey('subSections')
        ->and($nav['sections'][6]['subSections'])->toHaveCount(1)
        ->and($nav['sections'][6]['subSections'][0]['groups'])->toHaveCount(5);
});

test('display sections expose platform pages under new groupings', function () {
    $service = app(DocsService::class);
    $sections = collect($service->getNavigation()['sections']);

    $crm = $sections->firstWhere('slug', 'crm');
    expect(array_column($crm['pages'], 'slug'))->toBe(['members', 'activities', 'calendar']);

    $jobPlanning = $sections->firstWhere('slug', 'job-planning');
    expect(array_column($jobPlanning['pages'], 'slug'))->toBe(['opportunities', 'availability', 'shortages', 'planner']);

    $resources = $sections->firstWhere('slug', 'resources');
    expect(array_column($resources['pages'], 'slug'))->toBe([
        'products', 'product-groups', 'stock-levels', 'stock-transactions', 'accessories',
    ]);

    $operations = $sections->firstWhere('slug', 'operations');
    expect(array_column($operations['pages'], 'slug'))->toContain('api-console', 'dashboard', 'lists');
});

test('flat sections still expose pages without groups', function () {
    $service = app(DocsService::class);
    $gettingStarted = collect($service->getNavigation()['sections'])->firstWhere('slug', 'getting-started');

    expect($gettingStarted)->not->toHaveKey('groups')
        ->and($gettingStarted['pages'])->toHaveCount(6)
        ->and(array_column($gettingStarted['pages'], 'slug'))->not->toContain('sso', 'magic-link');
});

test('pageExists returns true for valid section and page', function () {
    $service = app(DocsService::class);

    expect($service->pageExists('getting-started', 'introduction'))->toBeTrue();
});

test('pageExists returns false for non-existent section', function () {
    $service = app(DocsService::class);

    expect($service->pageExists('nonexistent', 'page'))->toBeFalse();
});

test('pageExists returns false for non-existent page in valid section', function () {
    $service = app(DocsService::class);

    expect($service->pageExists('getting-started', 'nonexistent'))->toBeFalse();
});

test('pageExists rejects path traversal attempts', function () {
    $service = app(DocsService::class);

    expect($service->pageExists('..', '.env'))->toBeFalse()
        ->and($service->pageExists('getting-started', '../../.env'))->toBeFalse();
});

test('getPage returns title html toc and description', function () {
    $service = app(DocsService::class);
    $page = $service->getPage('getting-started', 'introduction');

    expect($page)->toBeArray()
        ->and($page)->toHaveKeys(['title', 'html', 'toc', 'description'])
        ->and($page['title'])->toBe('Introduction')
        ->and($page['description'])->toBe('Signals Rental Framework is an open-source rental management framework. Free, self-hostable, and plugin-extensible.')
        ->and($page['html'])->toContain('<h2')
        ->and($page['toc'])->toBeArray()->toHaveCount(8);
});

test('getPage returns null for non-existent page', function () {
    $service = app(DocsService::class);

    expect($service->getPage('nonexistent', 'page'))->toBeNull();
});

test('extractTableOfContents extracts h2 and h3 headings', function () {
    $service = app(DocsService::class);
    $html = '<h2 id="first">First</h2><p>Text</p><h3 id="nested">Nested</h3><h2 id="second">Second</h2>';
    $toc = $service->extractTableOfContents($html);

    expect($toc)->toHaveCount(3)
        ->and($toc[0])->toMatchArray(['level' => 2, 'id' => 'first', 'text' => 'First'])
        ->and($toc[1])->toMatchArray(['level' => 3, 'id' => 'nested', 'text' => 'Nested'])
        ->and($toc[2])->toMatchArray(['level' => 2, 'id' => 'second', 'text' => 'Second']);
});

test('extractTableOfContents returns empty array for empty html', function () {
    $service = app(DocsService::class);

    expect($service->extractTableOfContents(''))->toBeEmpty();
});

test('extractTableOfContents ignores headings without id', function () {
    $service = app(DocsService::class);
    $html = '<h2>No ID</h2><h2 id="with-id">With ID</h2>';
    $toc = $service->extractTableOfContents($html);

    expect($toc)->toHaveCount(1)
        ->and($toc[0]['id'])->toBe('with-id');
});

test('getAdjacentPages returns correct previous and next', function () {
    $service = app(DocsService::class);
    $adjacent = $service->getAdjacentPages('getting-started', 'installation');

    expect($adjacent['prev'])->not->toBeNull()
        ->and($adjacent['prev']['slug'])->toBe('introduction')
        ->and($adjacent['next'])->not->toBeNull()
        ->and($adjacent['next']['slug'])->toBe('configuration');
});

test('first page has null prev', function () {
    $service = app(DocsService::class);
    $adjacent = $service->getAdjacentPages('getting-started', 'introduction');

    expect($adjacent['prev'])->toBeNull()
        ->and($adjacent['next'])->not->toBeNull();
});

test('getSearchIndex returns enriched entries for all pages and changelog', function () {
    $service = app(DocsService::class);
    $index = $service->getSearchIndex();

    $reachablePages = count(array_filter(
        $service->getAllPageRouteKeys(),
        fn (array $routeKey): bool => $service->pageExists($routeKey['urlSection'], $routeKey['slug']),
    ));

    $expectedCount = $reachablePages + count($service->getChangelog());

    expect($index)->toBeArray()->toHaveCount($expectedCount)
        ->and($index[0])->toHaveKeys(['title', 'section', 'sectionSlug', 'group', 'slug', 'url', 'description', 'headings', 'content'])
        ->and($index[0]['title'])->toBe('Introduction')
        ->and($index[0]['section'])->toBe('Getting Started')
        ->and($index[0]['sectionSlug'])->toBe('getting-started')
        ->and($index[0]['group'])->toBeNull()
        ->and($index[0]['slug'])->toBe('introduction')
        ->and($index[0]['description'])->not->toBeEmpty()
        ->and($index[0]['headings'])->toContain('What is Signals Rental Framework?')
        ->and($index[0]['content'])->toContain('production-ready rental management framework');

    $membersEntry = collect($index)->first(fn (array $entry): bool => $entry['sectionSlug'] === 'crm' && $entry['slug'] === 'members');
    expect($membersEntry)->not->toBeNull()
        ->and($membersEntry['section'])->toBe('CRM')
        ->and($membersEntry['url'])->toBe(route('docs.show', ['platform', 'members']))
        ->and($membersEntry['group'])->toBeNull();

    $ssoEntry = collect($index)->first(fn (array $entry): bool => $entry['slug'] === 'sso');
    expect($ssoEntry)->toBeNull();

    $changelogEntry = collect($index)->first(fn (array $entry): bool => $entry['slug'] === 'changelog-0.5.1');
    expect($changelogEntry)->not->toBeNull()
        ->and($changelogEntry['title'])->toBe('0.5.1')
        ->and($changelogEntry['section'])->toBe('Changelog')
        ->and($changelogEntry['url'])->toBe(route('docs.changelog').'#v0.5.1')
        ->and($changelogEntry['content'])->not->toBeEmpty();

    expect(collect($index)->firstWhere('slug', 'changelog'))->toBeNull();
});

test('getSearchIndex includes per-version changelog entries with anchor urls', function () {
    $service = app(DocsService::class);
    $index = $service->getSearchIndex();

    $versions = collect($service->getChangelog())->pluck('version');

    expect($versions)->not->toBeEmpty();

    foreach ($versions as $version) {
        $entry = collect($index)->first(fn (array $item): bool => $item['slug'] === 'changelog-'.$version);
        expect($entry)->not->toBeNull()
            ->and($entry['title'])->toBe($version)
            ->and($entry['url'])->toBe(route('docs.changelog').'#v'.$version);
    }
});

test('getSearchIndex excludes manifest pages whose markdown file is missing', function () {
    $dir = sys_get_temp_dir().'/docs-search-index-ghost-'.uniqid();
    mkdir($dir, 0755, true);

    file_put_contents($dir.'/documentation.json', json_encode([
        'sections' => [[
            'title' => 'Guide',
            'slug' => 'guide',
            'pages' => [[
                'title' => 'Ghost Page',
                'slug' => 'ghost',
                'description' => 'Listed in manifest only',
            ]],
        ]],
    ], JSON_THROW_ON_ERROR));

    $service = new DocsService($dir);

    expect($service->getSearchIndex())->toBeEmpty();

    unlink($dir.'/documentation.json');
    rmdir($dir);
});

test('getSearchIndex derives headings from rendered page table of contents', function () {
    $service = app(DocsService::class);
    $index = $service->getSearchIndex();

    $introduction = collect($index)->first(fn (array $entry): bool => $entry['slug'] === 'introduction');

    expect($introduction)->not->toBeNull()
        ->and($introduction['headings'])->toContain('What is Signals Rental Framework?');
});

test('resolveNavigation emits sub-section slugs for display context', function () {
    $service = app(DocsService::class);
    $development = collect($service->getNavigation()['sections'])->firstWhere('slug', 'development');

    expect($development['subSections'][0]['slug'])->toBe('api-reference');

    $context = $service->resolveDisplayContext('api', 'members');

    expect($context)->not->toBeNull()
        ->and($context['subSectionSlug'])->toBe('api-reference');
});

test('iterateResolvedNavigation includes pages from groups and subSections together', function () {
    $dir = sys_get_temp_dir().'/docs-nav-groups-subsections-'.uniqid();
    mkdir($dir.'/guide', 0755, true);
    mkdir($dir.'/api', 0755, true);

    file_put_contents($dir.'/guide/legacy.md', "# Legacy\n\n## Legacy Heading\n\nBody.");
    file_put_contents($dir.'/api/nested.md', "# Nested\n\n## Nested Heading\n\nBody.");

    file_put_contents($dir.'/documentation.json', json_encode([
        'sections' => [[
            'title' => 'Guide',
            'slug' => 'guide',
            'groups' => [[
                'label' => 'Legacy Group',
                'pages' => [[
                    'title' => 'Legacy Page',
                    'slug' => 'legacy',
                    'description' => 'Legacy grouped page',
                ]],
            ]],
            'subSections' => [[
                'label' => 'API Reference',
                'groups' => [[
                    'label' => 'Core',
                    'pages' => [[
                        'title' => 'Nested Page',
                        'slug' => 'nested',
                        'urlSection' => 'api',
                        'description' => 'Nested API page',
                    ]],
                ]],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR));

    $service = new DocsService($dir);

    expect($service->pageExists('guide', 'legacy'))->toBeTrue()
        ->and($service->pageExists('api', 'nested'))->toBeTrue()
        ->and($service->getAllPageRouteKeys())->toHaveCount(2);

    $index = $service->getSearchIndex();
    expect(collect($index)->pluck('slug')->all())->toContain('legacy', 'nested');

    unlink($dir.'/documentation.json');
    unlink($dir.'/guide/legacy.md');
    unlink($dir.'/api/nested.md');
    rmdir($dir.'/guide');
    rmdir($dir.'/api');
    rmdir($dir);
});

test('getSearchIndex includes blade pages with description and empty headings', function () {
    $service = app(DocsService::class);
    $index = $service->getSearchIndex();

    $bladeEntry = collect($index)->first(fn (array $entry): bool => $entry['slug'] === 'library');

    expect($bladeEntry)->not->toBeNull()
        ->and($bladeEntry['title'])->toBe('Component Library')
        ->and($bladeEntry['sectionSlug'])->toBe('development')
        ->and($bladeEntry['description'])->not->toBeEmpty()
        ->and($bladeEntry['headings'])->toBe([])
        ->and($bladeEntry['content'])->toBe('');
});

test('last page has null next', function () {
    $service = app(DocsService::class);
    $adjacent = $service->getAdjacentPages('api', 'activities');

    expect($adjacent['next'])->toBeNull()
        ->and($adjacent['prev'])->not->toBeNull();
});

test('getChangelog returns entries sorted newest first', function () {
    $service = app(DocsService::class);
    $entries = $service->getChangelog();

    expect($entries)->toBeArray()
        ->and(count($entries))->toBeGreaterThan(0)
        ->and($entries[0])->toHaveKeys(['version', 'date', 'title', 'html'])
        ->and($entries[0]['version'])->toBe('0.5.1');
});

test('getChangelog extracts frontmatter correctly', function () {
    $service = app(DocsService::class);
    $entries = $service->getChangelog();

    expect($entries[0]['version'])->toBe('0.5.1')
        ->and($entries[0]['date'])->toBe('2026-06-28')
        ->and($entries[0]['title'])->toBe('Planning Interface Polish — Overlap Planner, Multi-Store Availability & Product Bookings');
});

test('getChangelog renders markdown to html', function () {
    $service = app(DocsService::class);
    $entries = $service->getChangelog();

    expect($entries[0]['html'])->toContain('<h3')
        ->and($entries[0]['html'])->toContain('Added');
});

test('changelogExists returns true when entries exist', function () {
    $service = app(DocsService::class);

    expect($service->changelogExists())->toBeTrue();
});

test('pageExists returns true for blade-type pages', function () {
    $service = app(DocsService::class);

    expect($service->pageExists('development', 'library'))->toBeTrue();
});

test('getPage returns blade metadata for blade-type pages', function () {
    $service = app(DocsService::class);
    $page = $service->getPage('development', 'library');

    expect($page)->toBeArray()
        ->and($page['type'])->toBe('blade')
        ->and($page['view'])->toBe('docs.components.library')
        ->and($page['title'])->toBe('Component Library')
        ->and($page['html'])->toBeNull()
        ->and($page['toc'])->toBeEmpty();
});

test('every manifest page is registered and reachable', function () {
    $service = app(DocsService::class);

    foreach ($service->getAllPageRouteKeys() as $routeKey) {
        expect($service->pageExists($routeKey['urlSection'], $routeKey['slug']))->toBeTrue(
            "Expected {$routeKey['urlSection']}/{$routeKey['slug']} to be registered",
        );
    }
});

test('getPage returns markdown content for development getting-started', function () {
    $service = app(DocsService::class);
    $page = $service->getPage('development', 'getting-started');

    expect($page)->toBeArray()
        ->and($page['title'])->toBe('Getting Started')
        ->and($page['html'])->toContain('Component System Overview')
        ->and($page['toc'])->not->toBeEmpty();
});

test('getLandingCards returns flat sections and grouped subsections', function () {
    $service = app(DocsService::class);
    $cards = $service->getLandingCards();

    expect($cards)->toHaveCount(12)
        ->and($cards[0])->toHaveKeys(['title', 'description', 'icon', 'sectionSlug', 'sectionTitle', 'pages', 'viewAllUrl'])
        ->and($cards[0]['title'])->toBe('Getting Started')
        ->and($cards[0]['pages'])->toHaveCount(4)
        ->and(collect($cards)->pluck('title')->all())->toContain('CRM', 'Pricing & Tax', 'Development', 'Core Resources');
});

test('getLatestChangelogVersion returns the newest changelog filename', function () {
    $service = app(DocsService::class);

    expect($service->getLatestChangelogVersion())->toBe('0.5.1');
});

test('getSitemapUrls excludes retired auth pages', function () {
    $service = app(DocsService::class);
    $urls = $service->getSitemapUrls();

    expect($urls)->not->toContain(route('docs.show', ['getting-started', 'sso']))
        ->and($urls)->not->toContain(route('docs.show', ['getting-started', 'magic-link']))
        ->and($urls)->toContain(route('docs.show', ['getting-started', 'authentication']));
});
