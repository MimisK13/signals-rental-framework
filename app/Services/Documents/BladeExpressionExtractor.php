<?php

namespace App\Services\Documents;

use App\Exceptions\Documents\DocumentRenderException;

/**
 * Extracts PHP expression snippets from Blade template source for sandbox validation.
 */
class BladeExpressionExtractor
{
    /** @var list<string> */
    private const LOOP_DIRECTIVES = [
        'foreach',
        'forelse',
    ];

    /** @var list<string> */
    private const COMPILED_DIRECTIVES = [
        'if',
        'elseif',
        'unless',
        'isset',
        'empty',
        'while',
        'switch',
        'case',
        'for',
        'json',
        'lang',
        'choice',
        'can',
        'cannot',
        'canany',
        'error',
        'enderror',
    ];

    /**
     * @return list<string>
     */
    public function extract(string $template): array
    {
        $template = $this->stripComments($template);

        $expressions = [];

        foreach ($this->extractEchoExpressions($template) as $expression) {
            $expressions[] = $expression;
        }

        foreach ($this->extractDirectiveExpressions($template) as $expression) {
            $expressions[] = $expression;
        }

        return $expressions;
    }

    private function stripComments(string $template): string
    {
        return (string) preg_replace('/\{\{--[\s\S]*?--\}\}/', '', $template);
    }

    /**
     * @return list<string>
     */
    private function extractEchoExpressions(string $template): array
    {
        $expressions = [];
        $length = strlen($template);
        $offset = 0;
        $iterations = 0;
        $maxIterations = max(1, $length) * 2;

        while ($offset < $length) {
            if (++$iterations > $maxIterations) {
                throw DocumentRenderException::forbidden('Template parsing exceeded safe iteration limit.');
            }

            $escapedEcho = strpos($template, '@{{', $offset);
            $rawEcho = strpos($template, '{!!', $offset);
            $echo = strpos($template, '{{', $offset);

            $next = $this->minOffset($escapedEcho, $rawEcho, $echo);

            if ($next === null) {
                break;
            }

            if ($escapedEcho !== false && $next === $escapedEcho) {
                $offset = $next + 3;

                continue;
            }

            if ($rawEcho !== false && $next === $rawEcho) {
                $expression = $this->readDelimitedExpression($template, $next + 3, '!!}');

                if ($expression !== null) {
                    $expressions[] = $expression;
                    $offset = $next + strlen($expression) + 5;

                    continue;
                }
            }

            if ($echo !== false && $next === $echo) {
                $expression = $this->readDelimitedExpression($template, $next + 2, '}}');

                if ($expression !== null) {
                    $expressions[] = $expression;
                    $offset = $next + strlen($expression) + 4;

                    continue;
                }
            }

            $offset = $next + 1;
        }

        return $expressions;
    }

    /**
     * @return list<string>
     */
    private function extractDirectiveExpressions(string $template): array
    {
        $expressions = [];
        $length = strlen($template);
        $offset = 0;
        $iterations = 0;
        $maxIterations = max(1, $length) * 2;

        while ($offset < $length) {
            if (++$iterations > $maxIterations) {
                throw DocumentRenderException::forbidden('Template parsing exceeded safe iteration limit.');
            }

            $at = strpos($template, '@', $offset);

            if ($at === false) {
                break;
            }

            if ($at > 0 && $template[$at - 1] === '@') {
                $offset = $at + 1;

                continue;
            }

            if (! preg_match('/\G@([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $template, $matches, 0, $at)) {
                $offset = $at + 1;

                continue;
            }

            $directive = strtolower($matches[1]);
            $openParen = $at + strlen($matches[0]) - 1;
            $inner = $this->readParenthesisedExpression($template, $openParen);

            if ($inner === null) {
                $offset = $at + 1;

                continue;
            }

            if (in_array($directive, self::LOOP_DIRECTIVES, true)) {
                array_push($expressions, ...$this->splitLoopExpression($inner));
            } elseif (in_array($directive, self::COMPILED_DIRECTIVES, true)) {
                if ($directive === 'for') {
                    array_push($expressions, ...$this->splitForExpression($inner));
                } else {
                    $expressions[] = $inner;
                }
            }

            $offset = $openParen + strlen($inner) + 2;
        }

        return $expressions;
    }

    private function readDelimitedExpression(string $template, int $start, string $terminator): ?string
    {
        $terminatorPosition = strpos($template, $terminator, $start);

        if ($terminatorPosition === false) {
            return null;
        }

        return trim(substr($template, $start, $terminatorPosition - $start));
    }

    private function readParenthesisedExpression(string $template, int $openParen): ?string
    {
        if ($template[$openParen] !== '(') {
            return null;
        }

        $depth = 0;
        $length = strlen($template);

        for ($index = $openParen; $index < $length; $index++) {
            $character = $template[$index];

            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;

                if ($depth === 0) {
                    return trim(substr($template, $openParen + 1, $index - $openParen - 1));
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function splitLoopExpression(string $expression): array
    {
        if (! preg_match('/^(.+?)\s+as\s+(.+)$/s', $expression, $matches)) {
            return [$expression];
        }

        $expressions = [trim($matches[1])];
        $targets = trim($matches[2]);

        if (str_contains($targets, '=>')) {
            [$key, $value] = array_map(trim(...), explode('=>', $targets, 2));
            $expressions[] = $key;
            $expressions[] = $value;
        } else {
            $expressions[] = $targets;
        }

        return $expressions;
    }

    /**
     * @return list<string>
     */
    private function splitForExpression(string $expression): array
    {
        return array_values(array_filter(array_map(
            trim(...),
            explode(';', $expression),
        ), fn (string $part): bool => $part !== ''));
    }

    private function minOffset(int|false|null ...$offsets): ?int
    {
        $valid = [];

        foreach ($offsets as $offset) {
            if ($offset !== false && $offset !== null) {
                $valid[] = $offset;
            }
        }

        if ($valid === []) {
            return null;
        }

        return min($valid);
    }
}
