<?php

use App\Contracts\Documents\PdfDriver;
use App\Services\Documents\Drivers\BrowsershotDriver;
use App\Services\Documents\Drivers\DompdfDriver;
use App\Services\Documents\PdfPageLayout;
use App\ValueObjects\Documents\PdfDocumentParts;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $pageConfig
 */
function dompdfLayoutHtml(string $bodyHtml, PdfDocumentParts $parts, array $pageConfig = []): string
{
    $driver = new DompdfDriver;
    $method = new ReflectionMethod(DompdfDriver::class, 'buildRepeatingLayoutHtml');
    $method->setAccessible(true);

    /** @var string $html */
    $html = $method->invoke($driver, $bodyHtml, $parts, $pageConfig);

    return $html;
}

function naivePdfText(string $pdfBinary): string
{
    $text = '';

    foreach (decompressedPdfStreams($pdfBinary) as $stream) {
        if (preg_match_all('/\(([^()\\\\]*(?:\\\\.[^()\\\\]*)*)\)/s', $stream, $matches) === false) {
            continue;
        }

        foreach ($matches[1] as $match) {
            $text .= stripcslashes($match).' ';
        }
    }

    return $text;
}

/**
 * @return list<string>
 */
function decompressedPdfStreams(string $pdfBinary): array
{
    if (preg_match_all("/stream\r?\n(.*?)\r?\nendstream/s", $pdfBinary, $matches) === false) {
        return [];
    }

    $streams = [];

    foreach ($matches[1] as $stream) {
        $decoded = @gzuncompress($stream);

        if ($decoded === false) {
            $decoded = @gzuncompress(substr($stream, 2));
        }

        if ($decoded !== false) {
            $streams[] = $decoded;
        }
    }

    return $streams;
}

function pdfStreamsContaining(string $pdfBinary, string $needle): int
{
    return collect(decompressedPdfStreams($pdfBinary))
        ->filter(fn (string $stream): bool => str_contains($stream, $needle))
        ->count();
}

function pdfPageCount(string $pdfBinary): int
{
    preg_match_all('/\/Type\s*\/Page(?!s)/', $pdfBinary, $matches);

    return count($matches[0]);
}

function multiPageBodyHtml(int $lines = 80): string
{
    return collect(range(1, $lines))
        ->map(fn (int $line): string => '<p>Body line '.$line.': '.str_repeat('Content ', 12).'</p>')
        ->implode("\n");
}

describe('DompdfDriver repeating header and footer', function () {
    it('builds fixed-position header and footer containers with body-only flow content', function () {
        $parts = new PdfDocumentParts(
            header: '<span class="brand">Repeat Header Marker</span>',
            footer: '<small>Repeat Footer Marker</small>',
            brandingCss: ':root { --primary-colour: #000; }',
            stylesCss: '.brand { color: var(--primary-colour); }',
        );

        $html = dompdfLayoutHtml('<p>Body only</p>', $parts);

        expect($html)
            ->toContain('position: fixed')
            ->toContain('pdf-page-header')
            ->toContain('pdf-page-footer')
            ->toContain('Repeat Header Marker')
            ->toContain('Repeat Footer Marker')
            ->toContain('<div class="document-body"><p>Body only</p></div>');

        expect($html)->not->toContain('<div class="document-header">');
    });

    it('omits fixed header markup when the header fragment is empty', function () {
        $parts = new PdfDocumentParts(
            header: '',
            footer: '<small>Footer only</small>',
            brandingCss: '',
            stylesCss: '',
        );

        $html = dompdfLayoutHtml('<p>Body only</p>', $parts);

        expect($html)
            ->not->toContain('pdf-page-header')
            ->toContain('pdf-page-footer');
    });

    it('renders multi-page PDFs with header and footer text present in the output', function () {
        $headerMarker = 'P4C7-HEADER-'.Str::uuid()->toString();
        $footerMarker = 'P4C7-FOOTER-'.Str::uuid()->toString();
        $parts = new PdfDocumentParts(
            header: '<strong>'.$headerMarker.'</strong>',
            footer: '<em>'.$footerMarker.'</em>',
            brandingCss: 'body { font-size: 12px; }',
            stylesCss: '',
        );

        $driver = new DompdfDriver;
        $pdf = $driver->render(multiPageBodyHtml(), [], $parts);

        expect($pdf)->toStartWith('%PDF')
            ->and(pdfPageCount($pdf))->toBeGreaterThanOrEqual(2)
            ->and(pdfStreamsContaining($pdf, $headerMarker))->toBeGreaterThanOrEqual(2)
            ->and(pdfStreamsContaining($pdf, $footerMarker))->toBeGreaterThanOrEqual(2);

        $extracted = naivePdfText($pdf);

        expect($extracted)
            ->toContain($headerMarker)
            ->toContain($footerMarker);
    });

    it('does not duplicate the header in the body flow on a single-page PDF', function () {
        $headerMarker = 'P4C7-SINGLE-HEADER-'.Str::uuid()->toString();
        $parts = new PdfDocumentParts(
            header: '<strong>'.$headerMarker.'</strong>',
            footer: '<small>Footer</small>',
            brandingCss: 'body { font-size: 12px; }',
            stylesCss: '',
        );

        $driver = new DompdfDriver;
        $pdf = $driver->render('<p>Short single-page body</p>', [], $parts);

        expect(pdfPageCount($pdf))->toBe(1)
            ->and(pdfStreamsContaining($pdf, $headerMarker))->toBe(1)
            ->and(substr_count(naivePdfText($pdf), $headerMarker))->toBe(1);
    });
});

describe('BrowsershotDriver header and footer templates', function () {
    it('passes header and footer templates with inlined branding CSS to Chromium', function () {
        $driver = new class extends BrowsershotDriver
        {
            /** @var array<string, mixed> */
            public static array $captured = [];

            public function render(string $html, array $pageConfig, ?PdfDocumentParts $parts = null): string
            {
                if ($parts === null) {
                    return '%PDF-browsershot-test';
                }

                $hasHeader = $parts->hasHeader();
                $hasFooter = $parts->hasFooter();
                $margins = PdfPageLayout::pageMargins($pageConfig, $hasHeader, $hasFooter);

                return $this->renderWithBrowsershot(
                    html: $this->wrapBodyHtml($html, $parts),
                    pageSize: (string) ($pageConfig['page_size'] ?? 'A4'),
                    orientation: (string) ($pageConfig['page_orientation'] ?? 'portrait'),
                    margins: $margins,
                    headerTemplate: $hasHeader
                        ? $this->wrapChromiumTemplate($parts->header, $parts)
                        : null,
                    footerTemplate: $hasFooter
                        ? $this->wrapChromiumTemplate($parts->footer, $parts)
                        : null,
                );
            }

            /**
             * @param  array{top: float, right: float, bottom: float, left: float}  $margins
             */
            protected function renderWithBrowsershot(
                string $html,
                string $pageSize,
                string $orientation,
                array $margins,
                ?string $headerTemplate,
                ?string $footerTemplate,
            ): string {
                self::$captured = compact(
                    'html',
                    'pageSize',
                    'orientation',
                    'margins',
                    'headerTemplate',
                    'footerTemplate',
                );

                return '%PDF-browsershot-test';
            }
        };

        $parts = new PdfDocumentParts(
            header: '<span class="brand">Browsershot Header</span>',
            footer: '<small>Browsershot Footer</small>',
            brandingCss: ':root { --primary-colour: #123456; }',
            stylesCss: '.brand { color: var(--primary-colour); }',
        );

        $pdf = $driver->render('<p>Body content</p>', [], $parts);

        expect($pdf)->toBe('%PDF-browsershot-test')
            ->and($driver::$captured['headerTemplate'])
            ->toContain('Browsershot Header')
            ->toContain('--primary-colour: #123456')
            ->toContain('.brand { color: var(--primary-colour); }')
            ->and($driver::$captured['footerTemplate'])
            ->toContain('Browsershot Footer')
            ->toContain('--primary-colour: #123456')
            ->and($driver::$captured['html'])
            ->toContain('<div class="document-body"><p>Body content</p></div>');

        expect($driver::$captured['html'])->not->toContain('Browsershot Header');
    });
});

describe('PdfDriver structured parts from the pipeline', function () {
    it('passes separated body and PdfDocumentParts to the active driver', function () {
        $driver = new class implements PdfDriver
        {
            /** @var array<string, mixed>|null */
            public static ?array $lastCall = null;

            public function render(string $html, array $pageConfig, ?PdfDocumentParts $parts = null): string
            {
                self::$lastCall = compact('html', 'pageConfig', 'parts');

                return '%PDF-spy';
            }
        };

        app()->instance(PdfDriver::class, $driver);

        $parts = new PdfDocumentParts(
            header: '<header>Spy Header</header>',
            footer: '<footer>Spy Footer</footer>',
            brandingCss: 'body {}',
            stylesCss: '.x {}',
        );

        app(PdfDriver::class)->render(
            '<p>Spy body</p>',
            ['page_size' => 'A4'],
            $parts,
        );

        expect($driver::$lastCall['html'])->toBe('<p>Spy body</p>')
            ->and($driver::$lastCall['parts'])->toBe($parts)
            ->and($driver::$lastCall['parts']->header)->toContain('Spy Header')
            ->and($driver::$lastCall['parts']->footer)->toContain('Spy Footer');
    });
});
