<?php

namespace App\Services\Documents;

use App\Exceptions\Documents\DocumentRenderException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Renders document template fragments in a sandboxed Blade context.
 *
 * Templates receive only the resolver data array (as objects for ergonomic
 * property access). No application container, facades, or request state is
 * available during evaluation.
 */
class SandboxedBladeRenderer
{
    public function __construct(
        private readonly SandboxedBladeCompiler $compiler,
        private readonly Filesystem $files,
        private readonly BladeExpressionExtractor $expressionExtractor,
        private readonly SandboxedExpressionValidator $expressionValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $template, array $data, string $cacheKey): string
    {
        if (trim($template) === '') {
            return '';
        }

        try {
            $this->guardTemplate($template);

            $compiledPath = $this->compiler->getCompiledPath($cacheKey);
            $compiled = $this->compiler->compileString($template);
            $this->files->put($compiledPath, $compiled);

            return $this->evaluateCompiled($compiledPath, $this->prepareTemplateData($data));
        } catch (DocumentRenderException $exception) {
            Log::warning('Document template render blocked', [
                'cache_key' => $cacheKey,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Document template render failed', [
                'cache_key' => $cacheKey,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw DocumentRenderException::compilationFailed();
        }
    }

    private function guardTemplate(string $template): void
    {
        foreach ($this->expressionExtractor->extract($template) as $expression) {
            $this->expressionValidator->validate($expression);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareTemplateData(array $data): array
    {
        $prepared = [];

        foreach ($data as $key => $value) {
            $prepared[$key] = $this->toTemplateValue($value);
        }

        return $prepared;
    }

    private function toTemplateValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return [];
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->toTemplateValue($item), $value);
        }

        $object = new \stdClass;

        foreach ($value as $key => $nested) {
            $object->{$key} = $this->toTemplateValue($nested);
        }

        return $object;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function evaluateCompiled(string $compiledPath, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();

        try {
            include $compiledPath;
        } finally {
            if (ob_get_level() > 0) {
                $output = ob_get_clean();
            } else {
                $output = '';
            }
        }

        return is_string($output) ? $output : '';
    }
}
