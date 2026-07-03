<?php

namespace App\Services\Documents;

use App\Actions\Documents\GenerateDocument;
use App\Contracts\Documents\DocumentRenderer;
use App\Enums\DocumentOutputFormat;
use App\Exceptions\Documents\DocumentRenderException;
use App\Models\DocumentTemplate;
use App\ValueObjects\Documents\DocumentRenderResult;
use Throwable;

/**
 * Five-stage document rendering pipeline: resolve → compile → assemble → output.
 *
 * The record stage is handled by {@see GenerateDocument}.
 */
class DocumentRenderPipeline implements DocumentRenderer
{
    public function __construct(
        private readonly DocumentResolverRegistry $resolverRegistry,
        private readonly SandboxedBladeRenderer $bladeRenderer,
        private readonly DocumentBrandingResolver $brandingResolver,
        private readonly PdfDriverManager $pdfDriverManager,
    ) {}

    public function render(
        DocumentTemplate $template,
        string $documentType,
        int $sourceId,
        DocumentOutputFormat $format = DocumentOutputFormat::Preview,
    ): DocumentRenderResult {
        try {
            $renderedData = $this->resolverRegistry->resolve($documentType, $sourceId);
        } catch (Throwable $exception) {
            return DocumentRenderResult::failure(
                'Unable to resolve document data.',
                $format,
            );
        }

        return $this->renderWithData($template, $renderedData, $format);
    }

    public function renderFromSnapshot(
        DocumentTemplate $template,
        array $renderedData,
        DocumentOutputFormat $format = DocumentOutputFormat::Preview,
    ): DocumentRenderResult {
        return $this->renderWithData($template, $renderedData, $format);
    }

    /**
     * @param  array<string, mixed>  $renderedData
     */
    private function renderWithData(
        DocumentTemplate $template,
        array $renderedData,
        DocumentOutputFormat $format,
    ): DocumentRenderResult {
        try {
            $template->loadMissing('store');

            if ($template->store === null) {
                return DocumentRenderResult::failure('Template store is missing.', $format);
            }

            $compiled = $this->compileSections($template, $renderedData);
            $branding = $this->brandingResolver->resolve($template, $template->store);
            $html = $this->assemble($compiled, $template, $branding);
            $pdfBinary = $this->output($html, $template, $format);

            return DocumentRenderResult::success($html, $renderedData, $format, $pdfBinary);
        } catch (DocumentRenderException $exception) {
            return DocumentRenderResult::failure($exception->getMessage(), $format);
        } catch (Throwable $exception) {
            report($exception);

            return DocumentRenderResult::failure(
                'Unable to render document template.',
                $format,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $renderedData
     * @return array{header: string, body: string, footer: string}
     */
    private function compileSections(DocumentTemplate $template, array $renderedData): array
    {
        $cachePrefix = 'document-template-'.$template->id.'-v'.$template->version;

        return [
            'header' => $this->bladeRenderer->render(
                (string) ($template->header_html ?? ''),
                $renderedData,
                $cachePrefix.'-header',
            ),
            'body' => $this->bladeRenderer->render(
                (string) $template->body_html,
                $renderedData,
                $cachePrefix.'-body',
            ),
            'footer' => $this->bladeRenderer->render(
                (string) ($template->footer_html ?? ''),
                $renderedData,
                $cachePrefix.'-footer',
            ),
        ];
    }

    /**
     * @param  array{header: string, body: string, footer: string}  $compiled
     * @param  array{primary_colour: string, secondary_colour: string, font_family: string, logo_url: ?string, css: string}  $branding
     */
    private function assemble(array $compiled, DocumentTemplate $template, array $branding): string
    {
        $styles = (string) ($template->styles_css ?? '');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
{$branding['css']}
{$styles}
</style>
</head>
<body>
<div class="document-header">{$compiled['header']}</div>
<div class="document-body">{$compiled['body']}</div>
<div class="document-footer">{$compiled['footer']}</div>
</body>
</html>
HTML;
    }

    private function output(
        string $html,
        DocumentTemplate $template,
        DocumentOutputFormat $format,
    ): ?string {
        if ($format === DocumentOutputFormat::Preview || $format === DocumentOutputFormat::HtmlDownload) {
            return null;
        }

        $pageConfig = $template->page_config ?? config('documents.defaults.page', []);

        return $this->pdfDriverManager->driver()->render($html, $pageConfig);
    }
}
