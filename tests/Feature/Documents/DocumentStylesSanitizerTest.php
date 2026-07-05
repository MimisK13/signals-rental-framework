<?php

use App\Contracts\Documents\DocumentRenderer;
use App\Enums\DocumentOutputFormat;
use App\Exceptions\Documents\DocumentRenderException;
use App\Models\DocumentTemplate;
use App\Models\Opportunity;
use App\Models\Store;
use App\Services\Documents\DocumentStylesSanitizer;
use App\Services\Documents\SystemDocumentTemplates;

describe('DocumentStylesSanitizer', function () {
    beforeEach(function () {
        $this->sanitizer = app(DocumentStylesSanitizer::class);
    });

    it('allows legitimate template CSS including CSS at-rules', function () {
        $styles = '.brand { color: var(--primary-colour); } @media print { .brand { color: black; } }';

        expect($this->sanitizer->sanitize($styles))->toBe($styles);
    });

    it('allows every system template stylesheet definition', function () {
        foreach (SystemDocumentTemplates::definitions() as $definition) {
            expect(fn () => $this->sanitizer->sanitize($definition['styles_css']))
                ->not->toThrow(DocumentRenderException::class);
        }
    });

    it('rejects Blade echo syntax in styles_css', function () {
        expect(fn () => $this->sanitizer->sanitize('.x { content: "{{ config(\'app.key\') }}"; }'))
            ->toThrow(DocumentRenderException::class, 'disallowed template syntax');
    });

    it('rejects Blade directives in styles_css', function () {
        expect(fn () => $this->sanitizer->sanitize('@php echo 1; @endphp'))
            ->toThrow(DocumentRenderException::class, 'disallowed template directives');
    });

    it('rejects PHP open tags in styles_css', function () {
        expect(fn () => $this->sanitizer->sanitize('<?php system("id"); ?>'))
            ->toThrow(DocumentRenderException::class, 'disallowed PHP syntax');
    });

    it('rejects style-tag markup breakout attempts', function () {
        expect(fn () => $this->sanitizer->sanitize('</style><img src="https://evil.test/leak">'))
            ->toThrow(DocumentRenderException::class, 'cannot contain HTML markup');
    });

    it('rejects remote url() references in styles_css', function () {
        expect(fn () => $this->sanitizer->sanitize('.x { background: url("https://evil.test/leak"); }'))
            ->toThrow(DocumentRenderException::class, 'cannot reference remote resources');
    });

    it('rejects remote @import references in styles_css', function () {
        expect(fn () => $this->sanitizer->sanitize('@import url("https://evil.test/leak.css");'))
            ->toThrow(DocumentRenderException::class, 'cannot import remote resources');
    });
});

describe('DocumentRenderPipeline styles_css integration', function () {
    it('blocks malicious styles_css during preview rendering', function () {
        $store = Store::factory()->create();
        $opportunity = Opportunity::factory()->for($store)->create(['subject' => 'Styles guard']);
        $template = DocumentTemplate::factory()->for($store)->create([
            'document_type' => 'quote',
            'model_type' => Opportunity::class,
            'body_html' => '<p>{{ $opportunity->subject }}</p>',
            'styles_css' => '.x { background: url("https://evil.test/leak"); }',
        ]);

        $result = app(DocumentRenderer::class)->render(
            $template->fresh('store'),
            'opportunity',
            $opportunity->id,
            DocumentOutputFormat::Preview,
        );

        expect($result->success)->toBeFalse()
            ->and($result->error)->toContain('cannot reference remote resources');
    });

    it('locks dompdf remote fetching off in application config', function () {
        expect(config('dompdf.options.enable_remote'))->toBeFalse();
    });
});
