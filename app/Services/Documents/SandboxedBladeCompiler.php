<?php

namespace App\Services\Documents;

use App\Exceptions\Documents\DocumentRenderException;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Dedicated Blade compiler for document templates with dangerous directives disabled.
 *
 * Uses an isolated cache path so compiled document templates never share the
 * application view cache.
 */
class SandboxedBladeCompiler extends BladeCompiler
{
    protected function compilePhp($expression): never
    {
        throw DocumentRenderException::forbidden('Raw PHP blocks are not allowed in document templates.');
    }

    protected function compileInject($expression): never
    {
        throw DocumentRenderException::forbidden('Service injection is not allowed in document templates.');
    }

    protected function compileInclude($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileIncludeIf($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileIncludeWhen($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileIncludeUnless($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileIncludeFirst($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileIncludeIsolated($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileExtends($expression): never
    {
        throw DocumentRenderException::forbidden('Layout inheritance is not allowed in document templates.');
    }

    protected function compileExtendsFirst($expression): never
    {
        throw DocumentRenderException::forbidden('Layout inheritance is not allowed in document templates.');
    }

    protected function compileComponent($expression): never
    {
        throw DocumentRenderException::forbidden('Blade components are not allowed in document templates.');
    }

    protected function compileComponentFirst($expression): never
    {
        throw DocumentRenderException::forbidden('Blade components are not allowed in document templates.');
    }

    /**
     * @param  string  $value
     */
    protected function storePhpBlocks($value): never
    {
        throw DocumentRenderException::forbidden('Raw PHP blocks are not allowed in document templates.');
    }
}
