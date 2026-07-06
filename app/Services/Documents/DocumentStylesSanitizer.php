<?php

namespace App\Services\Documents;

use App\Exceptions\Documents\DocumentRenderException;

/**
 * Validates user-supplied template CSS before it is embedded in rendered documents.
 *
 * Unlike header/body/footer HTML, styles_css bypasses {@see SandboxedBladeRenderer}
 * and is injected verbatim into the assembled HTML. Treat it as untrusted input.
 */
class DocumentStylesSanitizer
{
    /**
     * @var list<string>
     */
    private const BLADE_DIRECTIVES = [
        'php', 'endphp', 'foreach', 'endforeach', 'forelse', 'endforelse',
        'for', 'endfor', 'while', 'endwhile', 'if', 'elseif', 'else', 'endif',
        'include', 'extends', 'section', 'endsection', 'yield', 'component',
        'endcomponent', 'slot', 'endslot', 'once', 'endonce', 'push', 'endpush',
        'stack', 'verbatim', 'endverbatim', 'json', 'isset', 'empty', 'auth',
        'endauth', 'guest', 'endguest', 'can', 'endcan', 'cannot', 'endcannot',
        'switch', 'endswitch', 'case', 'break', 'default', 'continue', 'unless',
        'endunless',
    ];

    public function sanitize(string $styles): string
    {
        if (trim($styles) === '') {
            return '';
        }

        $this->guardAgainstTemplateInjection($styles);
        $this->guardAgainstMarkupBreakout($styles);
        $this->guardAgainstRemoteResources($styles);

        return $styles;
    }

    private function guardAgainstTemplateInjection(string $styles): void
    {
        if (str_contains($styles, '{{') || str_contains($styles, '{!!')) {
            throw DocumentRenderException::forbidden('Document styles contain disallowed template syntax.');
        }

        if (preg_match('/<\?(?:php|=)?/i', $styles) === 1) {
            throw DocumentRenderException::forbidden('Document styles contain disallowed PHP syntax.');
        }

        $directives = implode('|', self::BLADE_DIRECTIVES);

        if (preg_match('/@(?:'.$directives.')\\b/i', $styles) === 1) {
            throw DocumentRenderException::forbidden('Document styles contain disallowed template directives.');
        }
    }

    private function guardAgainstMarkupBreakout(string $styles): void
    {
        if (preg_match('/<\\/?\\s*style\\b/i', $styles) === 1) {
            throw DocumentRenderException::forbidden('Document styles cannot contain HTML markup.');
        }
    }

    private function guardAgainstRemoteResources(string $styles): void
    {
        if (preg_match('/@import\\s+(?:url\\s*\\(\\s*)?(["\']?)https?:\\/\\//i', $styles) === 1) {
            throw DocumentRenderException::forbidden('Document styles cannot import remote resources.');
        }

        if (preg_match('/url\\s*\\(\\s*(["\']?)(?:https?:\\/\\/|\\/\\/)/i', $styles) === 1) {
            throw DocumentRenderException::forbidden('Document styles cannot reference remote resources.');
        }
    }
}
