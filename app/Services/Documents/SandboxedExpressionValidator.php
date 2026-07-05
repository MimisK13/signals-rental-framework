<?php

namespace App\Services\Documents;

use App\Exceptions\Documents\DocumentRenderException;

/**
 * Allowlist policy for PHP expressions embedded in document templates.
 *
 * Every {{ }}, {!! !!}, and Blade directive expression is tokenised and
 * validated before compilation. Only property/array access on injected data
 * and a small set of pure formatting helpers are permitted.
 */
class SandboxedExpressionValidator
{
    /** @var list<string> */
    public const ALLOWED_FUNCTIONS = [
        'abs',
        'count',
        'date',
        'e',
        'implode',
        'join',
        'max',
        'min',
        'nl2br',
        'number_format',
        'round',
        'strtolower',
        'strtoupper',
        'trim',
        'ucfirst',
    ];

    /** @return array<int, string> */
    private function forbiddenTokenMessages(): array
    {
        $messages = [
            T_DOUBLE_COLON => 'Scope resolution (::) is not allowed in document templates.',
            T_NS_SEPARATOR => 'Namespaced references are not allowed in document templates.',
            T_NEW => 'Object instantiation is not allowed in document templates.',
            T_EXIT => 'Exit/die is not allowed in document templates.',
            T_EVAL => 'Eval is not allowed in document templates.',
            T_INCLUDE => 'Include is not allowed in document templates.',
            T_INCLUDE_ONCE => 'Include is not allowed in document templates.',
            T_REQUIRE => 'Require is not allowed in document templates.',
            T_REQUIRE_ONCE => 'Require is not allowed in document templates.',
            T_ECHO => 'Echo is not allowed in document templates.',
            T_PRINT => 'Print is not allowed in document templates.',
        ];

        if (defined('T_ERROR_CONTROL')) {
            $messages[T_ERROR_CONTROL] = 'Error suppression (@) is not allowed in document templates.';
        }

        foreach (['T_NAME_FULLY_QUALIFIED', 'T_NAME_QUALIFIED', 'T_NAME_RELATIVE'] as $tokenName) {
            if (defined($tokenName)) {
                $messages[constant($tokenName)] = 'Namespaced references are not allowed in document templates.';
            }
        }

        return $messages;
    }

    public function validate(string $expression): void
    {
        $expression = trim($expression);

        if ($expression === '') {
            return;
        }

        if (str_contains($expression, '`')) {
            throw DocumentRenderException::forbidden('Backticks are not allowed in document templates.');
        }

        try {
            /** @var list<string|array{0: int, 1: string, 2: int}> $tokens */
            $tokens = token_get_all('<?php '.$expression.';', TOKEN_PARSE);
        } catch (\ParseError) {
            throw DocumentRenderException::forbidden('Invalid template expression syntax.');
        }

        if ($tokens === []) {
            throw DocumentRenderException::forbidden('Invalid template expression syntax.');
        }

        array_shift($tokens);

        $this->assertTokensAllowed($tokens);
    }

    /**
     * @param  list<string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function assertTokensAllowed(array $tokens): void
    {
        $forbiddenTokenMessages = $this->forbiddenTokenMessages();
        $count = count($tokens);
        $iterations = 0;
        $maxIterations = max(1, $count) * 2;

        for ($index = 0; $index < $count; $index++) {
            if (++$iterations > $maxIterations) {
                throw DocumentRenderException::forbidden('Expression validation exceeded safe iteration limit.');
            }

            $token = $tokens[$index];

            if (is_string($token)) {
                if ($token === '@') {
                    throw DocumentRenderException::forbidden('Error suppression (@) is not allowed in document templates.');
                }
                if ($token === '(') {
                    continue;
                }

                if ($token === ')') {
                    $next = $this->nextSignificantIndex($tokens, $index + 1, $count);

                    if ($next !== null && $tokens[$next] === '(') {
                        throw DocumentRenderException::forbidden('Dynamic function invocation is not allowed in document templates.');
                    }

                    continue;
                }

                continue;
            }

            $id = $token[0];

            if (isset($forbiddenTokenMessages[$id])) {
                throw DocumentRenderException::forbidden($forbiddenTokenMessages[$id]);
            }

            if ($id === T_OBJECT_OPERATOR) {
                $methodIndex = $this->nextSignificantIndex($tokens, $index + 1, $count);

                if ($methodIndex !== null
                    && is_array($tokens[$methodIndex])
                    && $tokens[$methodIndex][0] === T_STRING) {
                    $openParen = $this->nextSignificantIndex($tokens, $methodIndex + 1, $count);

                    if ($openParen !== null && $tokens[$openParen] === '(') {
                        throw DocumentRenderException::forbidden('Method calls are not allowed in document templates.');
                    }
                }

                continue;
            }

            if ($id === T_STRING) {
                $previous = $this->previousSignificantIndex($tokens, $index - 1);
                $isPropertyName = $previous !== null
                    && is_array($tokens[$previous])
                    && $tokens[$previous][0] === T_OBJECT_OPERATOR;

                if ($isPropertyName) {
                    continue;
                }

                $next = $this->nextSignificantIndex($tokens, $index + 1, $count);

                if ($next !== null && $tokens[$next] === '(') {
                    $name = strtolower($token[1]);

                    if (! in_array($name, self::ALLOWED_FUNCTIONS, true)) {
                        throw DocumentRenderException::forbidden(
                            "Function [{$token[1]}] is not allowed in document templates.",
                        );
                    }
                }

                continue;
            }

            if ($id === T_VARIABLE) {
                $next = $this->nextSignificantIndex($tokens, $index + 1, $count);

                if ($next !== null && $tokens[$next] === '(') {
                    throw DocumentRenderException::forbidden('Variable functions are not allowed in document templates.');
                }
            }
        }
    }

    /**
     * @param  list<string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function nextSignificantIndex(array $tokens, int $start, int $count): ?int
    {
        for ($index = $start; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_string($token)) {
                return $index;
            }

            if (! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  list<string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function previousSignificantIndex(array $tokens, int $start): ?int
    {
        for ($index = $start; $index >= 0; $index--) {
            $token = $tokens[$index];

            if (is_string($token)) {
                return $index;
            }

            if (! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $index;
            }
        }

        return null;
    }
}
