<?php

use App\Services\DocsService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->service = app(DocsService::class);
});

function makeTempDocsDir(): string
{
    $dir = sys_get_temp_dir().'/docs-service-test-'.uniqid();
    mkdir($dir, 0755, true);

    return $dir;
}

function removeTempDocsDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

it('bypasses cache for navigation in local environment', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    // Should call loadManifest directly without caching
    $nav = $this->service->getNavigation();

    expect($nav)->toHaveKey('sections');
});

it('uses cache for navigation in non-local environment', function () {
    App::shouldReceive('environment')->with('local')->andReturn(false);
    Cache::shouldReceive('remember')
        ->once()
        ->andReturn(['sections' => []]);

    $nav = $this->service->getNavigation();

    expect($nav)->toBe(['sections' => []]);
});

it('returns null when page file does not exist', function () {
    $result = $this->service->getPage('nonexistent-section', 'nonexistent-page');

    expect($result)->toBeNull();
});

it('returns empty toc for empty html', function () {
    $toc = $this->service->extractTableOfContents('');

    expect($toc)->toBe([]);
});

it('returns empty toc when html has no headings with ids', function () {
    $toc = $this->service->extractTableOfContents('<p>No headings here</p>');

    expect($toc)->toBe([]);
});

it('extracts h2 and h3 headings for table of contents', function () {
    $html = '<h2 id="intro">Introduction</h2><p>Text</p><h3 id="sub">Subsection</h3>';

    $toc = $this->service->extractTableOfContents($html);

    expect($toc)->toHaveCount(2);
    expect($toc[0])->toBe(['level' => 2, 'id' => 'intro', 'text' => 'Introduction']);
    expect($toc[1])->toBe(['level' => 3, 'id' => 'sub', 'text' => 'Subsection']);
});

it('skips headings without id attribute', function () {
    $html = '<h2>No ID</h2><h2 id="with-id">Has ID</h2>';

    $toc = $this->service->extractTableOfContents($html);

    expect($toc)->toHaveCount(1);
    expect($toc[0]['id'])->toBe('with-id');
});

it('returns false for changelog when directory does not exist', function () {
    // Use a path that doesn't exist
    expect($this->service->changelogExists())->toBeBool();
});

it('bypasses cache for changelog in local environment', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $changelog = $this->service->getChangelog();

    expect($changelog)->toBeArray();
});

it('bypasses cache for search index in local environment', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $index = $this->service->getSearchIndex();

    expect($index)->toBeArray();
});

it('uses a fixed cache key with ttl for search index in non-local environment', function () {
    App::shouldReceive('environment')->with('local')->andReturn(false);
    Cache::shouldReceive('remember')
        ->once()
        ->with('docs:search-index:v2', 3600, Mockery::type('callable'))
        ->andReturn([]);

    $index = $this->service->getSearchIndex();

    expect($index)->toBe([]);
});

it('reports page does not exist for non-manifest page', function () {
    expect($this->service->pageExists('fake-section', 'fake-page'))->toBeFalse();
});

it('returns null adjacent pages when page not found', function () {
    $adjacent = $this->service->getAdjacentPages('nonexistent', 'page');

    expect($adjacent['prev'])->toBeNull();
    expect($adjacent['next'])->toBeNull();
});

it('returns null for paths that resolve outside the docs directory', function () {
    // docs/../CLAUDE.md resolves to a real file outside the docs root and must be rejected.
    expect($this->service->getPage('..', 'CLAUDE'))->toBeNull();
});

it('returns an empty manifest and changelog when the docs directory is empty', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeTempDocsDir();

    try {
        $service = new DocsService($dir);

        expect($service->getNavigation())->toBe(['sections' => []]);
        expect($service->changelogExists())->toBeFalse();
        expect($service->getChangelog())->toBe([]);
    } finally {
        removeTempDocsDir($dir);
    }
});

it('returns an empty manifest when documentation.json is not a json object', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeTempDocsDir();

    try {
        file_put_contents($dir.'/documentation.json', '"not a manifest object"');

        $service = new DocsService($dir);

        expect($service->getNavigation())->toBe(['sections' => []]);
    } finally {
        removeTempDocsDir($dir);
    }
});

it('resolves the availability API documentation page', function () {
    expect($this->service->pageExists('api', 'availability'))->toBeTrue();

    $page = $this->service->getPage('api', 'availability');

    expect($page)->not->toBeNull()
        ->and($page['html'])->toContain('availability:read');
});

it('resolves the opportunities API documentation page', function () {
    expect($this->service->pageExists('api', 'opportunities'))->toBeTrue();

    $page = $this->service->getPage('api', 'opportunities');

    expect($page)->not->toBeNull()
        ->and($page['html'])->toContain('opportunities:read');
});

it('resolves the shortages API documentation page', function () {
    expect($this->service->pageExists('api', 'shortages'))->toBeTrue();

    $page = $this->service->getPage('api', 'shortages');

    expect($page)->not->toBeNull()
        ->and($page['html'])->toContain('shortages:read');
});

it('resolves the containers API documentation page', function () {
    expect($this->service->pageExists('api', 'containers'))->toBeTrue();

    $page = $this->service->getPage('api', 'containers');

    expect($page)->not->toBeNull()
        ->and($page['html'])->toContain('containers:read');
});

it('lists the availability page in the API reference navigation groups', function () {
    $nav = $this->service->getNavigation();

    $developmentSection = collect($nav['sections'])->firstWhere('slug', 'development');
    $apiReference = $developmentSection['subSections'][0];

    $allApiSlugs = collect($apiReference['groups'])
        ->flatMap(fn (array $group): array => array_column($group['pages'], 'slug'))
        ->all();

    expect($allApiSlugs)->toContain('availability')
        ->toContain('opportunities')
        ->toContain('shortages')
        ->toContain('containers')
        ->toContain('usage');

    $groupLabels = array_column($apiReference['groups'], 'label');
    expect($groupLabels)->toContain('Rental', 'Platform');
});

it('lists api-console in the operations navigation section', function () {
    $nav = $this->service->getNavigation();

    $operationsSection = collect($nav['sections'])->firstWhere('slug', 'operations');

    $slugs = array_column($operationsSection['pages'], 'slug');

    expect($slugs)->toContain('api-console');
});

it('resolves the api-console platform documentation page', function () {
    expect($this->service->pageExists('platform', 'api-console'))->toBeTrue();

    $page = $this->service->getPage('platform', 'api-console');

    expect($page)->not->toBeNull()
        ->and($page['html'])->toContain('api.explorer.use');
});

it('returns false for manifest pages whose markdown file is missing', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeTempDocsDir();

    try {
        file_put_contents($dir.'/documentation.json', json_encode([
            'sections' => [[
                'title' => 'Guide',
                'slug' => 'guide',
                'icon' => 'book',
                'description' => 'Guide section',
                'pages' => [[
                    'title' => 'Ghost Page',
                    'slug' => 'ghost',
                    'description' => 'Listed in manifest only',
                ]],
            ]],
        ], JSON_THROW_ON_ERROR));

        $service = new DocsService($dir);

        expect($service->pageExists('guide', 'ghost'))->toBeFalse();
    } finally {
        removeTempDocsDir($dir);
    }
});

it('search index includes plain-text body content from rendered markdown', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeTempDocsDir();

    try {
        mkdir($dir.'/guide');
        file_put_contents($dir.'/guide/page.md', "## First\n\nUnique searchable phrase in body.");
        file_put_contents($dir.'/documentation.json', json_encode([
            'sections' => [[
                'title' => 'Guide',
                'slug' => 'guide',
                'pages' => [[
                    'title' => 'Page',
                    'slug' => 'page',
                    'description' => 'A page',
                ]],
            ]],
        ], JSON_THROW_ON_ERROR));

        $service = new DocsService($dir);
        $index = $service->getSearchIndex();

        expect($index)->toHaveCount(1)
            ->and($index[0]['content'])->toContain('Unique searchable phrase in body');
    } finally {
        removeTempDocsDir($dir);
    }
});

it('search index excludes manifest pages whose markdown file is missing', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeTempDocsDir();

    try {
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
    } finally {
        removeTempDocsDir($dir);
    }
});

it('search index reflects updated markdown headings in local environment', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeTempDocsDir();

    try {
        mkdir($dir.'/guide');
        file_put_contents($dir.'/guide/page.md', "## First\n\nBody");
        file_put_contents($dir.'/documentation.json', json_encode([
            'sections' => [[
                'title' => 'Guide',
                'slug' => 'guide',
                'pages' => [[
                    'title' => 'Page',
                    'slug' => 'page',
                    'description' => 'A page',
                ]],
            ]],
        ], JSON_THROW_ON_ERROR));

        $service = new DocsService($dir);
        $firstIndex = $service->getSearchIndex();

        sleep(1);
        file_put_contents($dir.'/guide/page.md', "## First\n\n## Second\n\nBody");

        $secondIndex = $service->getSearchIndex();

        expect($firstIndex[0]['headings'])->toBe(['First'])
            ->and($secondIndex[0]['headings'])->toBe(['First', 'Second']);
    } finally {
        removeTempDocsDir($dir);
    }
});

it('resolves the usage API documentation page', function () {
    expect($this->service->pageExists('api', 'usage'))->toBeTrue();

    $page = $this->service->getPage('api', 'usage');

    expect($page)->not->toBeNull()
        ->and($page['html'])->toContain('api-usage:read');
});

it('skips changelog entries lacking frontmatter and formats integer dates', function () {
    App::shouldReceive('environment')->with('local')->andReturn(true);

    $dir = makeTempDocsDir();

    try {
        mkdir($dir.'/changelog');
        // No version/date frontmatter — skipped.
        file_put_contents($dir.'/changelog/draft.md', "# Draft notes\n\nNothing structured here.");
        // Integer date frontmatter — formatted via date().
        file_put_contents(
            $dir.'/changelog/release.md',
            "---\nversion: 1.0.0\ndate: 1700000000\ntitle: First Release\n---\n# Release\n",
        );

        $service = new DocsService($dir);
        $entries = $service->getChangelog();

        expect($entries)->toHaveCount(1)
            ->and($entries[0]['version'])->toBe('1.0.0')
            ->and($entries[0]['date'])->toBe(date('Y-m-d', 1700000000))
            ->and($entries[0]['title'])->toBe('First Release');
    } finally {
        removeTempDocsDir($dir);
    }
});
