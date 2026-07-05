<?php

use App\Services\DocsService;

use function Pest\Laravel\get;

test('all registered documentation URLs return 200', function () {
    $service = app(DocsService::class);

    foreach ($service->getAllPageRouteKeys() as $routeKey) {
        $response = get(route('docs.show', [$routeKey['urlSection'], $routeKey['slug']]));

        expect($response->status())->toBe(
            200,
            "Expected {$routeKey['urlSection']}/{$routeKey['slug']} to return 200",
        );
    }

    get(route('docs.changelog'))->assertOk();
});

test('legacy documentation URLs remain unchanged and return 200', function () {
    $legacyUrls = [
        ['getting-started', 'introduction'],
        ['getting-started', 'authentication'],
        ['platform', 'members'],
        ['platform', 'opportunities'],
        ['platform', 'api-console'],
        ['api', 'overview'],
        ['api', 'usage'],
        ['development', 'library'],
    ];

    foreach ($legacyUrls as [$section, $page]) {
        get(route('docs.show', [$section, $page]))->assertOk();
    }
});

test('retired auth pages redirect to authentication with 301', function () {
    get('/docs/getting-started/sso')
        ->assertRedirect(route('docs.show', ['getting-started', 'authentication']))
        ->assertStatus(301);

    get('/docs/getting-started/magic-link')
        ->assertRedirect(route('docs.show', ['getting-started', 'authentication']))
        ->assertStatus(301);
});

test('development placeholder pages return 200', function () {
    get(route('docs.show', ['development', 'workflows']))
        ->assertOk()
        ->assertSee('Coming soon');

    get(route('docs.show', ['development', 'plugin-sdk']))
        ->assertOk()
        ->assertSee('Coming soon');
});

test('manifest-only page without markdown file returns 404', function () {
    $dir = sys_get_temp_dir().'/docs-controller-test-'.uniqid();
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

    app()->instance(DocsService::class, new DocsService($dir));

    get(route('docs.show', ['guide', 'ghost']))->assertNotFound();

    unlink($dir.'/documentation.json');
    rmdir($dir);
});

test('docs index renders the landing page', function () {
    get(route('docs.index'))
        ->assertOk()
        ->assertViewIs('docs.index')
        ->assertSee('class="docs-landing"', false)
        ->assertSee('Browse Signals Rental Framework documentation', false)
        ->assertSee('CRM', false)
        ->assertSee('Job Planning', false)
        ->assertSee('Getting Started', false)
        ->assertSee('Latest release:', false)
        ->assertSee('0.6.0');
});

test('landing cards match manifest flat sections and groups', function () {
    $service = app(DocsService::class);
    $cards = $service->getLandingCards();

    expect($cards)->toHaveCount(16)
        ->and(collect($cards)->pluck('title')->all())->toContain('Getting Started', 'CRM', 'Development', 'Core Resources', 'Pricing & Tax', 'Billing');

    get(route('docs.index'))
        ->assertOk()
        ->assertSee('View all &rarr;', false)
        ->assertSee(route('docs.show', ['getting-started', 'introduction']), false)
        ->assertSee(route('docs.show', ['platform', 'members']), false);
});

test('docs index returns 404 when manifest has sections but no pages', function () {
    $dir = sys_get_temp_dir().'/docs-index-empty-pages-'.uniqid();
    mkdir($dir, 0755, true);

    file_put_contents($dir.'/documentation.json', json_encode([
        'sections' => [[
            'title' => 'Empty Section',
            'slug' => 'empty',
            'pages' => [],
        ]],
    ], JSON_THROW_ON_ERROR));

    app()->instance(DocsService::class, new DocsService($dir));

    get(route('docs.index'))->assertNotFound();

    unlink($dir.'/documentation.json');
    rmdir($dir);
});

test('docs index returns 404 when grouped sections contain no pages', function () {
    $dir = sys_get_temp_dir().'/docs-index-empty-groups-'.uniqid();
    mkdir($dir, 0755, true);

    file_put_contents($dir.'/documentation.json', json_encode([
        'sections' => [[
            'title' => 'Platform',
            'slug' => 'platform',
            'groups' => [[
                'label' => 'Core CRM',
                'pages' => [],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR));

    app()->instance(DocsService::class, new DocsService($dir));

    get(route('docs.index'))->assertNotFound();

    unlink($dir.'/documentation.json');
    rmdir($dir);
});

test('valid documentation page returns 200', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertViewIs('docs.show')
        ->assertSee('What is Signals Rental Framework?');
});

test('documentation page contains rendered markdown html', function () {
    get(route('docs.show', ['getting-started', 'installation']))
        ->assertOk()
        ->assertSee('Requirements')
        ->assertSee('Quick Start');
});

test('non-existent documentation page returns 404', function () {
    get(route('docs.show', ['nonexistent', 'page']))
        ->assertNotFound();
});

test('non-existent page in valid section returns 404', function () {
    get(route('docs.show', ['getting-started', 'nonexistent']))
        ->assertNotFound();
});

test('path traversal attempts return 404', function (string $section, string $page) {
    get("/docs/{$section}/{$page}")
        ->assertNotFound();
})->with([
    ['getting-started', 'introduction.php'],
    ['GETTING-STARTED', 'introduction'],
    ['getting_started', 'introduction'],
]);

test('docs page includes sidebar navigation', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('Getting Started')
        ->assertSee('Introduction')
        ->assertSee('Installation')
        ->assertSee('Configuration');
});

test('docs page includes table of contents', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('On This Page');
});

test('docs page includes previous and next links', function () {
    get(route('docs.show', ['getting-started', 'installation']))
        ->assertSee('Previous')
        ->assertSee('Next');
});

test('first docs page has no previous link', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertDontSee('&larr; Previous', false);
});

test('last docs page has no next link', function () {
    get(route('docs.show', ['api', 'activities']))
        ->assertDontSee('Next &rarr;', false);
});

test('docs pages are accessible without authentication', function () {
    $this->assertGuest();

    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk();
});

test('docs pages embed a single valid search index json payload', function () {
    $response = get(route('docs.show', ['getting-started', 'introduction']));

    $response->assertOk();

    $html = $response->getContent();

    expect(substr_count($html, 'id="docs-search-data"'))->toBe(1);

    preg_match('/<script id="docs-search-data" type="application\/json">(.*?)<\/script>/s', $html, $matches);

    expect($matches)->toHaveCount(2);

    $index = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

    assert(is_array($index));
    assert($index !== []);

    expect($index[0])->toHaveKeys(['title', 'section', 'sectionSlug', 'slug', 'url', 'description', 'headings', 'content']);
});

test('docs landing page includes the shared search index payload', function () {
    get(route('docs.index'))
        ->assertOk()
        ->assertSee('id="docs-search-data"', false)
        ->assertSee('docs-search-modal', false)
        ->assertSee('x-data="docsSearchModal', false)
        ->assertSee('class="docs-search-trigger"', false)
        ->assertDontSee('class="docs-landing-search"', false);
});

test('docs page uses standalone docs layout', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('Documentation')
        ->assertSee('SIGNALS');
});

test('docs page has proper html title', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('Signals Rental Framework - Documentation | Introduction', false);
});

test('docs image route serves existing images', function () {
    get(route('docs.image', ['path' => 'quick-start.png']))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

test('docs image route returns 404 for missing images', function () {
    get(route('docs.image', ['path' => 'nonexistent.png']))
        ->assertNotFound();
});

test('docs image route blocks path traversal', function () {
    get('/docs/images/../documentation.json')
        ->assertNotFound();
});

test('changelog route returns 200', function () {
    get(route('docs.changelog'))
        ->assertOk()
        ->assertViewIs('docs.changelog');
});

test('changelog page displays version numbers', function () {
    get(route('docs.changelog'))
        ->assertSee('0.5.1')
        ->assertSee('API Console')
        ->assertSee('0.1.0');
});

test('resolves the api-console platform documentation page', function () {
    get(route('docs.show', ['platform', 'api-console']))
        ->assertOk()
        ->assertViewIs('docs.show')
        ->assertSee('API Console')
        ->assertSee('api.explorer.use');
});

test('lists api-console in the operations navigation sidebar', function () {
    get(route('docs.show', ['platform', 'api-console']))
        ->assertOk()
        ->assertSee('data-section-slug="operations"', false)
        ->assertSee('API Console');
});

test('resolves the usage API documentation page', function () {
    get(route('docs.show', ['api', 'usage']))
        ->assertOk()
        ->assertViewIs('docs.show')
        ->assertSee('Usage &amp; Metering API', false)
        ->assertSee('api-usage:read');
});

test('lists usage in the API navigation sidebar', function () {
    get(route('docs.show', ['api', 'usage']))
        ->assertOk()
        ->assertSee('Usage &amp; Metering', false);
});

test('changelog page uses docs layout', function () {
    get(route('docs.changelog'))
        ->assertSee('Documentation')
        ->assertSee('SIGNALS');
});

test('changelog page includes sidebar with changelog link', function () {
    get(route('docs.changelog'))
        ->assertSee('Getting Started')
        ->assertSee('Changelog');
});

test('changelog page includes search modal markup', function () {
    get(route('docs.changelog'))
        ->assertSee('docs-search-modal', false);
});

test('changelog page is accessible without authentication', function () {
    $this->assertGuest();

    get(route('docs.changelog'))
        ->assertOk();
});

test('changelog page has proper html title', function () {
    get(route('docs.changelog'))
        ->assertSee('Signals Rental Framework - Documentation | Changelog', false);
});

test('docs sidebar includes changelog link on regular pages', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('Changelog');
});

test('sitemap returns valid xml with all docs pages and excludes the landing page', function () {
    get(route('docs.sitemap'))
        ->assertOk()
        ->assertHeader('content-type', 'application/xml')
        ->assertSee('<urlset', false)
        ->assertSee(route('docs.show', ['getting-started', 'introduction']), false)
        ->assertSee(route('docs.show', ['platform', 'members']), false)
        ->assertSee(route('docs.changelog'), false)
        ->assertDontSee(route('docs.show', ['getting-started', 'sso']), false)
        ->assertDontSee(route('docs.show', ['getting-started', 'magic-link']), false)
        ->assertDontSee('<loc>'.route('docs.index').'</loc>', false);
});

test('docs page includes meta description when frontmatter has description', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('<meta name="description" content="', false);
});

test('docs page includes canonical url', function () {
    $url = route('docs.show', ['getting-started', 'introduction']);

    get($url)
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.$url.'">', false);
});

test('docs page includes open graph meta tags', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:site_name" content="Signals Rental Framework">', false)
        ->assertSee('<meta property="og:title" content="Introduction">', false);
});

test('docs page includes twitter card meta tags', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<meta name="twitter:title" content="Introduction">', false);
});

test('docs page includes breadcrumb json-ld with section title', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('"@type": "BreadcrumbList"', false)
        ->assertSee('"name": "Getting Started"', false);
});

test('docs page includes website json-ld', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('"@type": "WebSite"', false)
        ->assertSee('"name": "Signals Rental Framework Documentation"', false);
});

test('changelog page includes meta description', function () {
    get(route('docs.changelog'))
        ->assertOk()
        ->assertSee('<meta name="description" content="All notable changes to Signals Rental Framework, ordered by version.">', false);
});

test('changelog page includes open graph meta tags', function () {
    get(route('docs.changelog'))
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Changelog">', false)
        ->assertSee('<meta property="og:type" content="article">', false);
});

test('changelog page does not include breadcrumb json-ld', function () {
    get(route('docs.changelog'))
        ->assertOk()
        ->assertDontSee('"@type": "BreadcrumbList"', false);
});

test('blade-type docs page returns 200 with blade view', function () {
    get(route('docs.show', ['development', 'library']))
        ->assertOk()
        ->assertViewIs('docs.blade')
        ->assertSee('Component Reference');
});

test('blade-type docs page includes sidebar navigation', function () {
    get(route('docs.show', ['development', 'library']))
        ->assertSee('Development')
        ->assertSee('Component Library');
});

test('blade-type docs page includes search modal markup', function () {
    get(route('docs.show', ['development', 'library']))
        ->assertSee('docs-search-modal', false);
});

test('blade-type docs page includes pagination', function () {
    get(route('docs.show', ['development', 'library']))
        ->assertSee('Previous');
});

test('development getting-started page returns 200', function () {
    get(route('docs.show', ['development', 'getting-started']))
        ->assertOk()
        ->assertViewIs('docs.show')
        ->assertSee('Component System Overview');
});

test('development section appears in sidebar', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertSee('Development')
        ->assertSee('Getting Started', false)
        ->assertSee('Component Library');
});

test('component-reference prototype redirects to docs', function () {
    get('/prototypes/component-reference')
        ->assertRedirect('/docs/development/library');
});

test('docs robots.txt route returns text content', function () {
    get(route('docs.robots'))
        ->assertOk()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap:');
});
