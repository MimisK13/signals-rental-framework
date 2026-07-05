<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DocsService;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocsController extends Controller
{
    public function __construct(
        private DocsService $docs,
    ) {}

    /**
     * Display the documentation landing page.
     */
    public function index(): View
    {
        $navigation = $this->docs->getNavigation();

        if (empty($navigation['sections']) || ! $this->navigationHasPages($navigation)) {
            abort(404);
        }

        return view('docs.index', [
            'navigation' => $navigation,
            'landingCards' => $this->docs->getLandingCards(),
            'latestChangelogVersion' => $this->docs->getLatestChangelogVersion(),
        ]);
    }

    /**
     * Serve robots.txt for the docs subdomain.
     */
    public function robots(): Response
    {
        $content = file_get_contents(public_path('robots.txt'));

        return response($content ?: '', 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Generate an XML sitemap for all documentation pages.
     */
    public function sitemap(): Response
    {
        $urls = $this->docs->getSitemapUrls();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1).'</loc></url>'."\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Serve an image from the docs/images directory.
     */
    public function image(string $path): BinaryFileResponse
    {
        $docsBase = realpath(base_path('docs/images'));

        abort_if($docsBase === false, 404);

        $filePath = realpath(base_path('docs/images/'.$path));

        abort_if($filePath === false || ! str_starts_with($filePath, $docsBase.DIRECTORY_SEPARATOR), 404);

        return response()->file($filePath);
    }

    /**
     * Display the changelog page with all version entries.
     */
    public function changelog(): View
    {
        abort_unless($this->docs->changelogExists(), 404);

        $entries = $this->docs->getChangelog();
        $navigation = $this->docs->getNavigation();

        return view('docs.changelog', [
            'entries' => $entries,
            'navigation' => $navigation,
        ]);
    }

    /**
     * Display a documentation page.
     */
    public function show(string $section, string $page): View
    {
        abort_unless($this->docs->pageExists($section, $page), 404);

        $content = $this->docs->getPage($section, $page);
        $navigation = $this->docs->getNavigation();
        $adjacent = $this->docs->getAdjacentPages($section, $page);
        $display = $this->docs->resolveDisplayContext($section, $page);

        abort_if($display === null, 404);

        $viewData = [
            'title' => $content['title'],
            'description' => $content['description'],
            'navigation' => $navigation,
            'currentSection' => $display['sectionSlug'],
            'currentSubSection' => $display['subSectionSlug'],
            'currentPage' => $page,
            'sectionTitle' => $display['sectionTitle'],
            'prev' => $adjacent['prev'],
            'next' => $adjacent['next'],
        ];

        if (($content['type'] ?? null) === 'blade') {
            return view('docs.blade', array_merge($viewData, [
                'bladeView' => $content['view'],
            ]));
        }

        return view('docs.show', array_merge($viewData, [
            'html' => $content['html'],
            'toc' => $content['toc'],
        ]));
    }

    /**
     * @param  array{sections: array<int, array{pages?: array<int, mixed>, subSections?: array<int, array{groups: array<int, array{pages?: array<int, mixed>}>}>}>}  $navigation
     */
    private function navigationHasPages(array $navigation): bool
    {
        foreach ($navigation['sections'] as $section) {
            if (isset($section['pages']) && $section['pages'] !== []) {
                return true;
            }

            if (! isset($section['subSections'])) {
                continue;
            }

            foreach ($section['subSections'] as $subSection) {
                foreach ($subSection['groups'] as $group) {
                    if (isset($group['pages']) && $group['pages'] !== []) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
