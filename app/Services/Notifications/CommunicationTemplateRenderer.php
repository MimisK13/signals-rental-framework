<?php

namespace App\Services\Notifications;

use App\Mail\TemplatedEmail;
use App\Models\CommunicationTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Renders communication templates with merge fields, optional caching, and channel formatting.
 */
class CommunicationTemplateRenderer
{
    public function __construct(
        private readonly MergeFieldRenderer $mergeFields,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{subject: string|null, body: string, html: string|null}
     */
    public function render(CommunicationTemplate $template, array $data, ?MergeFieldRenderContext $context = null): array
    {
        $context ??= MergeFieldRenderContext::defaults();
        $compiled = $this->compiledSegments($template);

        $subject = $template->subject !== null
            ? $this->mergeFields->renderCompiled($compiled['subject'], $data, $context)
            : null;

        $body = $this->mergeFields->renderCompiled($compiled['body'], $data, $context);

        if ($template->channel === 'email') {
            return [
                'subject' => $subject,
                'body' => $body,
                'html' => Str::markdown($body),
            ];
        }

        return [
            'subject' => $subject,
            'body' => $body,
            'html' => null,
        ];
    }

    /**
     * Render an email communication template wrapped in the branded layout.
     *
     * @param  array<string, mixed>  $data
     */
    public function renderBrandedEmail(
        CommunicationTemplate $template,
        array $data,
        ?string $eyebrow = null,
        ?string $footerContext = null,
        ?string $preheader = null,
        ?MergeFieldRenderContext $context = null,
    ): TemplatedEmail {
        $rendered = $this->render($template, $data, $context);

        return new TemplatedEmail(
            subjectLine: $rendered['subject'] ?? $template->name,
            bodyHtml: $rendered['html'] ?? '',
            eyebrow: $eyebrow,
            footerContext: $footerContext,
            preheader: $preheader,
        );
    }

    /**
     * @return array{subject: list<array<string, mixed>>, body: list<array<string, mixed>>}
     */
    public function compiledSegments(CommunicationTemplate $template): array
    {
        return Cache::remember(
            $this->cacheKeyForTemplate($template),
            now()->addDay(),
            fn (): array => [
                'subject' => $this->mergeFields->compile($template->subject ?? ''),
                'body' => $this->mergeFields->compile($template->body),
            ],
        );
    }

    public function invalidateCompiledCache(CommunicationTemplate $template, ?int $version = null): void
    {
        Cache::forget($this->cacheKey($template->id, $version ?? $template->version));
    }

    private function cacheKey(int $templateId, int $version): string
    {
        return "communication-template:compiled:{$templateId}:{$version}";
    }

    private function cacheKeyForTemplate(CommunicationTemplate $template): string
    {
        return $this->cacheKey($template->id, $template->version);
    }
}
