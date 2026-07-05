<?php

use function Pest\Laravel\get;

test('sidebar renders CRM section for platform member pages', function () {
    get(route('docs.show', ['platform', 'members']))
        ->assertOk()
        ->assertSee('data-section-slug="crm"', false)
        ->assertSee('>Members</a>', false);
});

test('sidebar renders nested API Reference groups on api pages', function () {
    get(route('docs.show', ['api', 'members']))
        ->assertOk()
        ->assertSee('data-section-slug="development"', false)
        ->assertSee('data-subsection-slug="api-reference"', false)
        ->assertSee('Core Resources', false)
        ->assertSee('Pricing &amp; Tax', false);
});

test('sidebar renders plain page titles inside navigation links', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('>Introduction</a>', false)
        ->assertDontSee('x-html=', false)
        ->assertDontSee('data-title=', false);
});

test('sidebar includes the alpine accordion controller', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('x-data="docsSidebar', false);
});

test('sidebar marks the display section and active page for legacy platform urls', function () {
    get(route('docs.show', ['platform', 'members']))
        ->assertOk()
        ->assertSee('data-section-slug="crm"', false)
        ->assertSee('class="docs-nav-section docs-nav-section--current"', false)
        ->assertSee('class="docs-nav-item active"', false)
        ->assertSee('>Members</a>', false);
});

test('sidebar includes a top-level changelog link', function () {
    get(route('docs.changelog'))
        ->assertOk()
        ->assertSee('class="docs-nav-changelog active"', false)
        ->assertSee('href="'.route('docs.changelog').'"', false);
});

test('sidebar sections use collapsible accordion toggles', function () {
    get(route('docs.show', ['getting-started', 'introduction']))
        ->assertOk()
        ->assertSee('class="docs-nav-section-toggle"', false)
        ->assertSee('class="docs-nav-chevron"', false)
        ->assertSee(':aria-expanded="isSectionOpen', false);
});

test('development section includes a nested api reference subsection', function () {
    $response = get(route('docs.show', ['development', 'library']));

    $response->assertOk()
        ->assertSee('class="docs-nav-subsection-toggle"', false)
        ->assertSee('class="docs-nav-subsection docs-nav-nest-1"', false)
        ->assertSee('class="docs-nav-subgroup docs-nav-nest-2"', false)
        ->assertSee('API Reference', false)
        ->assertSee('>Component Library</a>', false);
});

test('sidebar partial does not render duplicate element ids', function () {
    $html = get(route('docs.show', ['getting-started', 'introduction']))->getContent();

    preg_match_all('/\bid="([^"]+)"/', $html, $matches);

    $sidebarIds = array_filter($matches[1], fn (string $id): bool => str_starts_with($id, 'docs-sidebar') || str_starts_with($id, 'docs-nav'));

    expect($sidebarIds)->toBeEmpty();
});
