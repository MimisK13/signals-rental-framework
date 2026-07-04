<?php

use App\Models\User;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

/**
 * Asserts that, on the given page, the anchor pointing at $hrefRouteName is
 * rendered with the `active` class. Matches the first <a ...> opening tag whose
 * href is the route, regardless of surrounding attribute order.
 */
function assertNavLinkActive(string $html, string $hrefRouteName): void
{
    $href = route($hrefRouteName);

    // Capture every opening anchor tag in the document.
    preg_match_all('/<a\b[^>]*>/', $html, $matches);

    $matching = collect($matches[0])
        ->filter(fn (string $tag) => str_contains($tag, 'href="'.$href.'"'));

    expect($matching)->not->toBeEmpty("No anchor found for {$hrefRouteName}");

    expect($matching->contains(fn (string $tag) => str_contains($tag, 'active')))
        ->toBeTrue("Expected an active anchor for {$hrefRouteName}");
}

function adminSecondSidebarHtml(string $html): string
{
    expect(preg_match('/<nav class="s-admin-sidebar">(.*?)<\/nav>/s', $html, $matches))
        ->toBe(1, 'Expected the admin second sidebar nav');

    return $matches[1];
}

describe('first-level group active state (previously broken mappings)', function () {
    it('marks the Users group active on the API tokens page', function () {
        $html = $this->get(route('admin.settings.api'))->assertOk()->getContent();

        // First-level group link for Users & Security points at the users page.
        assertNavLinkActive($html, 'admin.settings.users');
    });

    it('marks the Preferences group active on the integrations page', function () {
        $html = $this->get(route('admin.settings.integrations'))->assertOk()->getContent();

        assertNavLinkActive($html, 'admin.settings.preferences');
    });

    it('marks the Documents group active on the document templates page', function () {
        $html = $this->get(route('admin.document-templates.index'))->assertOk()->getContent();

        assertNavLinkActive($html, 'admin.document-templates.index');
    });
});

describe('admin overview link', function () {
    it('renders an Overview link to the admin index in the second-level sidebar', function () {
        // The second-level admin sidebar component is rendered on settings sub-pages.
        $html = $this->get(route('admin.settings.api'))->assertOk()->getContent();

        $href = route('admin.index');
        expect(str_contains($html, 'href="'.$href.'"'))
            ->toBeTrue('Expected an Overview link to admin.index');
    });
});

describe('second-level item active state', function () {
    it('marks the API Tokens item active on the API page', function () {
        $html = $this->get(route('admin.settings.api'))->assertOk()->getContent();

        assertNavLinkActive($html, 'admin.settings.api');
    });

    it('marks the Integrations item active on the integrations page', function () {
        $html = $this->get(route('admin.settings.integrations'))->assertOk()->getContent();

        assertNavLinkActive($html, 'admin.settings.integrations');
    });

    it('does not render a webhooks item in the admin sidebar', function () {
        $html = $this->get(route('admin.settings.api'))->assertOk()->getContent();

        expect(preg_match('/s-admin-nav-item[^>]*href="'.preg_quote(route('admin.settings.webhooks'), '/').'"/', $html))
            ->toBe(0);
    });

    it('does not over-match Email when on the Email Templates page', function () {
        $html = $this->get(route('admin.settings.email-templates'))->assertOk()->getContent();

        // Email Templates item should be active...
        assertNavLinkActive($html, 'admin.settings.email-templates');

        // ...but the Email item (a path prefix) must NOT be active.
        $emailHref = route('admin.settings.email');
        preg_match_all('/<a\b[^>]*>/', $html, $matches);
        $emailTag = collect($matches[0])
            ->first(fn (string $tag) => str_contains($tag, 'href="'.$emailHref.'"'));

        expect($emailTag)->not->toBeNull();
        expect(str_contains($emailTag, 'active'))->toBeFalse();
    });
});

describe('contextual second sidebar sections', function () {
    it('shows only the documents section on the document templates page', function () {
        $html = $this->get(route('admin.document-templates.index'))->assertOk()->getContent();
        $sidebar = adminSecondSidebarHtml($html);

        expect($sidebar)->toContain('Documents');
        expect($sidebar)->toContain(route('admin.document-templates.index'));
        expect($sidebar)->not->toContain(route('admin.settings.tax.zones'));
        expect($sidebar)->not->toContain('Tax Zones');
        expect($sidebar)->not->toContain(route('admin.settings.users'));
    });

    it('shows only the tax section on the tax zones page', function () {
        $html = $this->get(route('admin.settings.tax.zones'))->assertOk()->getContent();
        $sidebar = adminSecondSidebarHtml($html);

        expect($sidebar)->toContain('Tax Zones');
        expect($sidebar)->toContain(route('admin.settings.tax.zones'));
        expect($sidebar)->not->toContain(route('admin.document-templates.index'));
        expect($sidebar)->not->toContain('Document Templates');
    });
});
